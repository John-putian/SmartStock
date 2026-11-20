<?php
/** FR-02, FR-03, FR-06 - inventory cataloguing, search and stock tracking. */
class InventoryController
{
    private const CONDITIONS = ['Class A', 'Class B', 'Class C', 'For Parts'];

    /** Returns a draft; normal inventory save remains a separate action. */
    public static function describe(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $file = $_FILES['photo'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                throw new InvalidArgumentException('Choose a JPG, PNG or WEBP photo up to 6 MB first.');
            }
            PhotoAssistant::image($file['tmp_name']);
            if (time() - (int) ($_SESSION['photo_ai_last'] ?? 0) < 15) {
                http_response_code(429);
                echo json_encode(['ok' => false, 'error' => 'Please wait 15 seconds before requesting another draft.']);
                return;
            }
            $_SESSION['photo_ai_last'] = time();
            $categories = Category::options();
            session_write_close(); // Do not hold other tabs or saving behind a slow AI request.
            $draft = PhotoAssistant::suggest($file['tmp_name'], $categories);
            $draft['price'] = PriceSuggestion::forItem($draft['name'], $draft['category_id']);
            echo json_encode(['ok' => true, 'draft' => $draft], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
        } catch (Throwable $error) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'Photo suggestions are unavailable. You can enter the details manually.']);
        }
    }

    /** Usable without an AI key, from entered name/category and local sales. */
    public static function suggestPrice(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $name = trim((string) input('name', ''));
        if ($name === '' || mb_strlen($name) > 150) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Enter an item name first (up to 150 characters).']);
            return;
        }
        try {
            echo json_encode(['ok' => true, 'price' => PriceSuggestion::forItem($name, (int) input('category_id', 0))], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $error) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'Price suggestions are unavailable. Enter the price manually.']);
        }
    }

    public static function index(): void
    {
        $filters = [
            'q'         => trim((string) input('q', '')),
            'category'  => input('category', ''),
            'condition' => input('condition', ''),
            'stock'     => input('stock', ''),
            'sort'      => input('sort', 'name'),
        ];
        $items = Product::search($filters);

        view('inventory/index', [
            'items'      => $items,
            'filters'    => $filters,
            'categories' => Category::optionsInUse(),   // filter: only stocked categories
            'conditions' => self::CONDITIONS,
            'counts'     => Product::counts(),
        ], 'Inventory');
    }

    public static function show(): void
    {
        $id = (int) input('id');
        $p  = Product::find($id);
        if (!$p) { flash('danger', 'Item not found.'); redirect('inventory'); }

        $sold = Database::one(
            'SELECT COALESCE(SUM(i.quantity),0) units, COALESCE(SUM(i.line_total),0) revenue
             FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE i.product_id = ?', [$id]) ?? ['units' => 0, 'revenue' => 0];

        view('inventory/show', [
            'p'            => $p,
            'units'        => ProductUnit::forProduct($id),
            'movements'    => Product::movements($id),
            'sold'         => $sold,
            'priceRequest' => PriceRequest::pendingForProduct($id),
        ], 'Item Details');
    }

    public static function create(): void
    {
        $errors = [];
        $item = [
            'id' => 0, 'sku' => '', 'serial_no' => '', 'name' => '', 'category_id' => '',
            'brand' => '', 'description' => '', 'condition_grade' => 'Class B',
            'cost_price' => '', 'selling_price' => '', 'quantity' => 1, 'image_path' => null,
            'reorder_level' => (int) setting('default_reorder_level', '3'),
        ];

        if (is_post()) {
            verify_csrf();
            $item = self::collect();
            $errors = self::validate($item);
            if (!$errors) {
                if (Database::value('SELECT COUNT(*) FROM products WHERE sku = ?', [$item['sku']])) {
                    $errors['sku'] = 'That SKU is already used by another item.';
                } else {
                    $photoError = null;
                    $item['image_path'] = self::handlePhoto($item['sku'], $photoError);
                    $id = Product::create($item);
                    if ($photoError) {
                        flash('warning', 'The item was saved, but the photo was not: ' . e($photoError));
                    }
                    flash('success', 'Item <strong>' . e($item['name']) . '</strong> has been added to the inventory.');
                    redirect('inventory.view', ['id' => $id]);
                }
            }
        }

        view('inventory/form', [
            'item' => $item, 'errors' => $errors, 'isEdit' => false,
            'categories' => Category::options(), 'conditions' => self::CONDITIONS,
        ], 'Add Inventory Item');
    }

    public static function edit(): void
    {
        $id   = (int) input('id');
        $item = Product::find($id);
        if (!$item) { flash('danger', 'Item not found.'); redirect('inventory'); }
        $errors = [];
        $listedPrice = (float) $item['selling_price'];

        if (is_post()) {
            verify_csrf();
            $posted = self::collect() + ['sku' => $item['sku']];
            $errors = self::validate($posted);
            if (!$errors) {
                $photoError = null;
                $new = self::handlePhoto($item['sku'], $photoError);
                if ($new) {
                    $posted['image_path'] = $new;
                } elseif (input('remove_photo')) {
                    $posted['image_path'] = null;
                } else {
                    $posted['image_path'] = $item['image_path'];   // keep what is on file
                }

                // The price guard. Everything else on the form saves as usual;
                // only the price itself can be held back for a decision.
                $held = Database::atomic(function () use ($id, &$posted) {
                    $current = Database::one('SELECT * FROM products WHERE id = ? FOR UPDATE', [$id]);
                    if (!$current) throw new RuntimeException('Item not found.');
                    $message = self::guardPriceChange($id, $current, $posted);
                    Product::update($id, $posted);
                    return $message;
                });
                if ($photoError) {
                    flash('warning', 'The item was saved, but the photo was not: ' . e($photoError));
                }
                if ($held === null) {
                    flash('success', 'Item <strong>' . e($posted['name']) . '</strong> has been updated.');
                } else {
                    flash('warning', $held);
                }
                redirect('inventory.view', ['id' => $id]);
            }
            $posted['image_path'] = $item['image_path'];
            $item = $posted + ['id' => $id, 'sku' => $item['sku']];
        }

        view('inventory/form', [
            'item' => $item, 'errors' => $errors, 'isEdit' => true,
            'categories' => Category::options(), 'conditions' => self::CONDITIONS,
            'pendingPrice' => PriceRequest::pendingForProduct($id),
            'listedPrice' => $listedPrice,
        ], 'Edit Inventory Item');
    }

    /**
     * The price guard on an item that is already listed.
     *
     * Two separate rules meet here:
     *
     *   1. A REDUCTION is where money quietly leaks, so nobody below manager
     *      may make one - work staff included.
     *   2. A CASHIER may not move a listed price at all, in either direction.
     *      They can price an item they are adding, because somebody has to; but
     *      once it is on the shelf the figure is not theirs to revise.
     *
     * Either way the rest of the form saves normally and only the price is held
     * back, filed as a request for an administrator or manager. Returns null
     * when nothing was held back, otherwise the message to show.
     */
    private static function guardPriceChange(int $id, array $current, array &$posted): ?string
    {
        $old = (float) $current['selling_price'];
        $new = (float) $posted['selling_price'];

        if (abs($new - $old) < 0.005 || Auth::canApprove()) {
            return null;                       // unchanged, or the person may decide
        }

        $lowering = $new < $old;
        if (!$lowering && Auth::canSetPrice()) {
            return null;                       // work staff and above may raise it
        }

        $posted['selling_price'] = $old;       // the listed price does not move
        $word = $lowering ? 'lower' : 'raise';

        if ($waiting = PriceRequest::pendingForProduct($id)) {
            return 'The other changes were saved. The price is still '
                 . peso($old) . ' &mdash; a change to '
                 . peso($waiting['new_price'])
                 . ' is already waiting for an administrator or manager to decide.';
        }

        PriceRequest::open($id, $old, $new, (string) input('price_reason', ''));
        return 'The other changes were saved, but the price stays at ' . peso($old)
             . '. Your request to ' . $word . ' it to ' . peso($new)
             . ' has been sent to the administrator or manager for approval.';
    }

    public static function archive(): void
    {
        $id = (int) input('id');
        Product::archive($id);
        flash('success', 'The item has been archived and removed from active listings.');
        redirect('inventory');
    }

    /** Receive additional stock for an existing item. */
    public static function stockIn(): void
    {
        verify_csrf();
        $id   = (int) input('id');
        $qty  = (int) input('qty');
        $note = trim((string) input('notes', ''));
        $p    = Product::find($id);

        if (!$p) {
            flash('danger', 'Item not found.');
        } elseif ($qty < 0 || ($qty === 0 && input('mode') !== 'adjust')) {
            flash('warning', 'Enter a positive quantity to receive, or a non-negative physical count.');
        } elseif (input('mode') === 'adjust') {
            Product::adjust($id, max(0, $qty), $note ?: 'Physical count adjustment');
            flash('success', 'Stock for <strong>' . e($p['name']) . '</strong> set to ' . $qty . ' unit(s).');
        } else {
            Product::stockIn($id, abs($qty), $note);
            flash('success', abs($qty) . ' unit(s) received for <strong>' . e($p['name']) . '</strong>.');
        }
        redirect('inventory.view', ['id' => $id]);
    }

    /**
     * FR-03 - barcode lookup for the POS scan field. A unit barcode
     * (AA-FUR-0007-03) identifies one exact piece; an item SKU or serial
     * falls back to the item itself.
     */
    public static function lookup(): void
    {
        header('Content-Type: application/json');
        $code = trim((string) input('code', ''));

        if ($u = ProductUnit::findByCode($code)) {
            echo json_encode(['found' => true, 'kind' => 'unit', 'unit' => [
                'id' => (int) $u['id'], 'code' => $u['unit_code'], 'name' => $u['name'],
                'status' => $u['status'], 'price' => $u['selling_price'],
            ]]);
            exit;
        }
        $p = Database::one(
            'SELECT id, sku, name, selling_price, quantity FROM products
             WHERE status = "active" AND (sku = ? OR serial_no = ?) LIMIT 1', [$code, $code]);
        echo json_encode($p
            ? ['found' => true, 'kind' => 'product', 'product' => $p]
            : ['found' => false]);
        exit;
    }

    /** Printable sheet of one QR label per physical piece. */
    public static function unitLabels(): void
    {
        $id = (int) input('id');
        $p  = Product::find($id);
        if (!$p) { flash('danger', 'Item not found.'); redirect('inventory'); }

        $units = ProductUnit::forProduct($id);
        if (input('only') === 'stock') {
            $units = array_values(array_filter($units, fn($u) => $u['status'] === 'in_stock'));
        }
        view_bare('inventory/unit_labels', ['p' => $p, 'units' => $units], 'Unit Labels');
    }

    /** Edit one piece's own condition grade and remarks. */
    public static function updateUnit(): void
    {
        verify_csrf();
        $unitId = (int) input('unit_id');
        $u      = ProductUnit::find($unitId);
        if (!$u) { flash('danger', 'That piece could not be found.'); redirect('inventory'); }

        $cond = in_array(input('condition_grade'), self::CONDITIONS, true)
              ? input('condition_grade') : null;
        ProductUnit::update($unitId, $cond, trim((string) input('notes', '')));
        flash('success', 'Piece <strong>' . e($u['unit_code']) . '</strong> updated.');
        redirect('inventory.view', ['id' => (int) $u['product_id']]);
    }

    /** FR-03 - QR code label for an item (rendered as an inline SVG). */
    public static function qr(): void
    {
        $id = (int) input('id');
        $p  = Product::find($id);
        if (!$p) { flash('danger', 'Item not found.'); redirect('inventory'); }
        view_bare('inventory/qr', ['p' => $p], 'QR Label');
    }

    public static function export(): void
    {
        $items = Product::search([
            'q' => input('q', ''), 'category' => input('category', ''),
            'condition' => input('condition', ''), 'stock' => input('stock', ''),
        ]);
        $rows = [];
        foreach ($items as $i) {
            [$label] = stock_status((int) $i['quantity'], (int) $i['reorder_level']);
            $rows[] = [
                $i['sku'], $i['name'], $i['category_name'], $i['brand'],
                $i['condition_grade'],
                (float) $i['cost_price'], (float) $i['selling_price'],
                (int) $i['quantity'], (int) $i['reorder_level'], $label,
                // A real number, not a formatted string: Excel has to be able
                // to total this column, and a thousands separator makes it text.
                round((float) $i['quantity'] * (float) $i['cost_price'], 2),
            ];
        }
        Audit::log('Inventory', 'EXPORT', 'Exported the inventory list');
        export_table('AA_inventory_' . date('Y-m-d'), [
            'title'    => 'A&A SURPLUS - Household Appliances Trading, Panabo City',
            'subtitle' => 'Inventory Catalogue  |  as at ' . date('F j, Y g:i A')
                        . '  |  prepared by ' . (Auth::user()['full_name'] ?? ''),
            'sheet'    => 'Inventory',
            'columns'  => [
                ['label' => 'SKU',           'type' => 'text', 'width' => 16],
                ['label' => 'Item Name',     'type' => 'text', 'width' => 38],
                ['label' => 'Category',      'type' => 'text', 'width' => 22],
                ['label' => 'Brand',         'type' => 'text', 'width' => 16],
                ['label' => 'Condition',     'type' => 'text', 'width' => 12],
                ['label' => 'Cost Price',    'type' => 'money'],
                ['label' => 'Selling Price', 'type' => 'money'],
                ['label' => 'Quantity',      'type' => 'int'],
                ['label' => 'Reorder Level', 'type' => 'int', 'width' => 13],
                ['label' => 'Status',        'type' => 'text', 'width' => 14],
                ['label' => 'Stock Value',   'type' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => ['TOTAL', null, null, null, null, null, null, 'sum', null, null, 'sum'],
        ]);
    }

    // ------------------------------------------------------------ helpers

    /**
     * Store an uploaded photograph of the unit and return its path relative to
     * assets/img, or null when nothing usable was uploaded. The file type is
     * verified from the image data itself, not from the name the browser sent.
     */
    private static function handlePhoto(string $sku, ?string &$error = null): ?string
    {
        $f = $_FILES['photo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'The photo could not be uploaded (it may be larger than the server allows).';
            return null;
        }
        if ($f['size'] > 6 * 1024 * 1024) {
            $error = 'The photo is larger than 6 MB.';
            return null;
        }

        $info = @getimagesize($f['tmp_name']);
        $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext) {
            $error = 'Only JPG, PNG or WEBP images can be uploaded.';
            return null;
        }

        $dir = BASE_PATH . '/assets/img/items';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $error = 'The image folder could not be created on the server.';
            return null;
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sku)) ?: 'item';
        $name = $slug . '-' . bin2hex(random_bytes(12)) . '.' . $ext;

        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            $error = 'The photo could not be saved to the image folder.';
            return null;
        }
        @chmod($dir . '/' . $name, 0644);
        return 'items/' . $name;
    }

    private static function collect(): array
    {
        return [
            'sku'             => trim((string) input('sku', '')),
            'serial_no'       => trim((string) input('serial_no', '')) ?: null,
            'name'            => trim((string) input('name', '')),
            'category_id'     => input('category_id') ? (int) input('category_id') : null,
            'brand'           => trim((string) input('brand', '')) ?: null,
            'description'     => trim((string) input('description', '')),
            'condition_grade' => in_array(input('condition_grade'), self::CONDITIONS, true)
                                   ? input('condition_grade') : 'Class B',
            'cost_price'      => (float) input('cost_price', 0),
            'selling_price'   => (float) input('selling_price', 0),
            'quantity'        => (int) input('quantity', 0),
            'reorder_level'   => max(0, (int) input('reorder_level', 3)),
            'image_path'      => null,
        ];
    }

    private static function validate(array $d): array
    {
        $e = [];
        if (mb_strlen($d['name']) > 150) $e['name'] = 'Item name must be 150 characters or fewer.';
        if (mb_strlen($d['sku']) > 40) $e['sku'] = 'SKU must be 40 characters or fewer.';
        if (mb_strlen($d['brand'] ?? '') > 80) $e['brand'] = 'Brand must be 80 characters or fewer.';
        if (mb_strlen($d['serial_no'] ?? '') > 60) $e['serial_no'] = 'Serial number must be 60 characters or fewer.';
        if ($d['name'] === '')                 $e['name'] = 'Item name is required.';
        if (!$d['category_id'] || !Category::find((int) $d['category_id'])) $e['category_id'] = 'Please choose an existing category.';
        if (!is_finite($d['selling_price']) || $d['selling_price'] <= 0) $e['selling_price'] = 'Selling price must be a number greater than zero.';
        if (!is_finite($d['cost_price']) || $d['cost_price'] < 0) $e['cost_price'] = 'Cost price must be a non-negative number.';
        if ($d['quantity'] < 0)                $e['quantity'] = 'Quantity cannot be negative.';
        if ($d['selling_price'] > 0 && $d['cost_price'] > $d['selling_price']) {
            $e['selling_price'] = 'Selling price is lower than the cost price - please double-check the amounts.';
        }
        foreach (['selling_price'=>'Selling price', 'cost_price'=>'Acquisition cost'] as $field => $label) {
            $raw = trim((string) input($field, ''));
            if (($field === 'selling_price' || $raw !== '') && (!is_numeric($raw) || !is_finite((float) $raw))) {
                $e[$field] = $label . ' needs a valid number.';
            } elseif ($d[$field] > 9999999999.99) {
                $e[$field] = $label . ' is too large.';
            }
        }
        foreach (['quantity'=>'Quantity', 'reorder_level'=>'Reorder level'] as $field => $label) {
            $raw = trim((string) input($field, $field === 'reorder_level' ? '3' : ''));
            if (!preg_match('/^\d+$/D', $raw) || (float) $raw > 2147483647) {
                $e[$field] = $label . ' needs a non-negative whole number up to 2,147,483,647.';
            }
        }
        $file = $_FILES['photo'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (!is_string($file['tmp_name'] ?? null) || ($file['error'] ?? null) !== UPLOAD_ERR_OK) {
                $e['photo'] = 'The photo could not be uploaded. Choose a photo up to 6 MB and try again.';
            } elseif (($file['size'] ?? 0) > 6 * 1024 * 1024) {
                $e['photo'] = 'Choose a photo up to 6 MB.';
            } else {
                $info = @getimagesize($file['tmp_name']);
                if (!in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    $e['photo'] = 'Choose a valid JPG, PNG or WEBP photo.';
                }
            }
        }
        return $e;
    }
}
