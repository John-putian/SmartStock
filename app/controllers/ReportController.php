<?php
/** FR-07 - daily / weekly / monthly / annual reports. */
class ReportController
{
    /** Containers arrive about a month apart, so stock has to cover that long. */
    private const COVER_DAYS = 30;

    /** Resolve the requested reporting period into concrete dates. */
    private static function period(): array
    {
        $preset = (string) input('preset', 'month');
        $today  = date('Y-m-d');

        switch ($preset) {
            case 'today':     $from = $today; $to = $today; $group = 'day'; break;
            case 'yesterday': $from = $to = date('Y-m-d', strtotime('-1 day')); $group = 'day'; break;
            case 'week':      $from = date('Y-m-d', strtotime('monday this week')); $to = $today; $group = 'day'; break;
            case 'last7':     $from = date('Y-m-d', strtotime('-6 days')); $to = $today; $group = 'day'; break;
            case 'last30':    $from = date('Y-m-d', strtotime('-29 days')); $to = $today; $group = 'day'; break;
            case 'lastmonth':
                $from = date('Y-m-01', strtotime('first day of last month'));
                $to   = date('Y-m-t', strtotime('first day of last month'));
                $group = 'day'; break;
            case 'quarter':   $from = date('Y-m-d', strtotime('-89 days')); $to = $today; $group = 'week'; break;
            case 'year':      $from = date('Y-01-01'); $to = $today; $group = 'month'; break;
            case 'custom':
                $from  = (string) input('from', date('Y-m-01'));
                $to    = (string) input('to', $today);
                foreach ([$from, $to] as $date) {
                    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                        flash('warning', 'Choose valid start and end dates.');
                        redirect('reports.sales');
                    }
                }
                if ($from > $to) {
                    flash('warning', 'Start date must be on or before end date.');
                    redirect('reports.sales');
                }
                $days  = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400));
                $group = $days > 180 ? 'month' : ($days > 45 ? 'week' : 'day');
                break;
            case 'month':
            default:
                $preset = 'month';
                $from = date('Y-m-01'); $to = $today; $group = 'day'; break;
        }
        return ['preset' => $preset, 'from' => $from, 'to' => $to,
                'group' => in_array(input('group', $group), ['day','week','month','year'], true) ? input('group', $group) : $group];
    }

    public static function index(): void
    {
        redirect('reports.sales', ['preset' => input('preset', 'month')]);
    }

    /** Sales and revenue report. */
    public static function sales(): void
    {
        $p = self::period();
        view('reports/sales', [
            'p'        => $p,
            'summary'  => Sale::summary($p['from'], $p['to']),
            'trend'    => Sale::trend($p['from'], $p['to'], $p['group']),
            'top'      => Sale::topProducts($p['from'], $p['to'], 10),
            'byCat'    => Sale::byCategory($p['from'], $p['to']),
            'byCash'   => Sale::byCashier($p['from'], $p['to']),
            'byPay'    => Sale::byPayment($p['from'], $p['to']),
            'sales'    => Sale::history($p['from'], $p['to'], ['q' => input('q', '')]),
            'q'        => input('q', ''),
        ], 'Sales & Revenue Report');
    }

    /**
     * FR-10 - Category Sales.
     *
     * Built for the monthly meeting with the surplus shipment owner. Stock
     * cannot be ordered by name - a container holds whatever it holds - so
     * the only request that can actually be made is "send us more of this
     * category". This screen therefore reports the day-by-day takings of
     * every category and turns that into a plain request list.
     */
    public static function category(): void
    {
        $p    = self::period();
        $rows = Sale::categoryPerformance($p['from'], $p['to']);
        $days = max(1, (int) ((strtotime($p['to']) - strtotime($p['from'])) / 86400) + 1);

        $revenueTotal = 0.0;
        $unitsTotal   = 0;
        foreach ($rows as $r) {
            $revenueTotal += (float) $r['revenue'];
            $unitsTotal   += (int) $r['units'];
        }

        // Work out, per category, what the shop should ask for next time.
        foreach ($rows as &$r) {
            $units   = (int) $r['units'];
            $left    = (int) $r['pieces_left'];
            $revenue = (float) $r['revenue'];

            $r['share']      = $revenueTotal > 0 ? $revenue / $revenueTotal * 100 : 0.0;
            $r['avg_price']  = $units > 0 ? $revenue / $units : 0.0;
            $r['margin']     = $r['cost'] === null ? null : $revenue - (float) $r['cost'];
            $r['per_day']    = $units / $days;
            $r['sell_rate']  = ($units + $left) > 0 ? $units / ($units + $left) * 100 : 0.0;
            $r['days_cover'] = $r['per_day'] > 0 ? $left / $r['per_day'] : null;

            // How many pieces to ask for so the category lasts until the next
            // container, roughly a month away. Anything already on the floor
            // counts against it.
            $r['suggest'] = (int) max(0, ceil($r['per_day'] * self::COVER_DAYS) - $left);

            [$r['advice'], $r['advice_tone'], $r['advice_why']] = self::categoryAdvice($r);
            if (!(int) $r['id']) {
                $r['suggest'] = 0;
                $r['advice'] = 'History only'; $r['advice_tone'] = 'steady';
                $r['advice_why'] = 'No current category can be matched to this historical total. No restock quantity is suggested.';
            }
        }
        unset($r);

        // Most urgent requests first, then by how much money the category makes.
        $order = ['more' => 0, 'keep' => 1, 'steady' => 2, 'less' => 3];
        usort($rows, function ($a, $b) use ($order) {
            $d = $order[$a['advice_tone']] <=> $order[$b['advice_tone']];
            if ($d !== 0) { return $d; }
            $d = (int) $b['suggest'] <=> (int) $a['suggest'];
            return $d !== 0 ? $d : ((float) $b['revenue'] <=> (float) $a['revenue']);
        });

        view('reports/category', [
            'p'        => $p,
            'days'     => $days,
            'rows'     => $rows,
            'daily'    => Sale::dailyByCategory($p['from'], $p['to']),
            'revenue'  => $revenueTotal,
            'units'    => $unitsTotal,
        ], 'Sales by Category');
    }

    /**
     * Turns one category's numbers into a sentence the manager can read out
     * in the meeting. Kept deliberately simple so the reasoning can be
     * explained and defended rather than taken on trust.
     */
    private static function categoryAdvice(array $r): array
    {
        $units = (int) $r['units'];
        $left  = (int) $r['pieces_left'];
        $cover = $r['days_cover'];

        if ($units > 0 && $left === 0) {
            return ['Ask for more', 'more',
                    'Everything received has sold and there is nothing left on the floor.'];
        }
        if ($units > 0 && $cover !== null && $cover < 14) {
            return ['Ask for more', 'more',
                    'At the current rate the remaining ' . num($left) . ' piece(s) last about '
                    . round($cover) . ' more day(s).'];
        }
        if ($units > 0 && $r['sell_rate'] >= 50) {
            return ['Keep receiving', 'keep',
                    round($r['sell_rate']) . '% of what was available sold in this period.'];
        }
        if ($units === 0 && $left > 0) {
            return ['Ask for less', 'less',
                    'Nothing sold this period and ' . num($left) . ' piece(s) are still sitting on the floor.'];
        }
        if ($units === 0 && $left === 0) {
            return ['Nothing to judge', 'steady',
                    'No stock held and none sold in this period.'];
        }
        return ['Steady', 'steady',
                'Selling slowly with ' . num($left) . ' piece(s) still in stock.'];
    }

    /** Inventory valuation / audit report. */
    public static function inventory(): void
    {
        $items = Database::all(
            'SELECT p.*, c.name AS category_name,
                    COALESCE(sold.units,0) sold_units, COALESCE(sold.revenue,0) sold_revenue
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN (SELECT i.product_id, SUM(i.quantity) units, SUM(i.line_total) revenue
                        FROM sale_items i GROUP BY i.product_id) sold ON sold.product_id = p.id
             WHERE p.status = "active"
             ORDER BY c.name, p.name');

        $byCat = Database::all(
            'SELECT COALESCE(c.name,"Uncategorised") category, COUNT(*) items,
                    COALESCE(SUM(p.quantity),0) units,
                    COALESCE(SUM(p.quantity * p.cost_price),0) cost_value,
                    COALESCE(SUM(p.quantity * p.selling_price),0) retail_value
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = "active" GROUP BY category ORDER BY cost_value DESC');

        view('reports/inventory', [
            'items'  => $items,
            'byCat'  => $byCat,
            'counts' => Product::counts(),
        ], 'Inventory Valuation Report');
    }

    /** Stock movement report. */
    public static function movement(): void
    {
        $p    = self::period();
        $type = (string) input('type', '');

        $sql  = 'SELECT m.*, p.name, p.sku, u.full_name
                 FROM stock_movements m
                 JOIN products p ON p.id = m.product_id
                 LEFT JOIN users u ON u.id = m.user_id
                 WHERE DATE(m.created_at) BETWEEN ? AND ?';
        $args = [$p['from'], $p['to']];
        if ($type !== '') { $sql .= ' AND m.type = ?'; $args[] = $type; }
        $sql .= ' ORDER BY m.created_at DESC, m.id DESC LIMIT 500';

        $totals = Database::all(
            'SELECT type, COUNT(*) entries, COALESCE(SUM(quantity),0) units
             FROM stock_movements WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY type',
            [$p['from'], $p['to']]);

        view('reports/movement', [
            'p' => $p, 'type' => $type,
            'rows' => Database::all($sql, $args),
            'totals' => $totals,
        ], 'Inventory Movement Report');
    }

    /** Printable consolidated report. */
    public static function printable(): void
    {
        $p = self::period();
        view_bare('reports/print', [
            'p'       => $p,
            'summary' => Sale::summary($p['from'], $p['to']),
            'trend'   => Sale::trend($p['from'], $p['to'], $p['group']),
            'top'     => Sale::topProducts($p['from'], $p['to'], 15),
            'byCat'   => Sale::byCategory($p['from'], $p['to']),
            'byCash'  => Sale::byCashier($p['from'], $p['to']),
            'byPay'   => Sale::byPayment($p['from'], $p['to']),
            'counts'  => Product::counts(),
            'alerts'  => Product::alerts(),
        ], 'Printable Report');
    }

    /** CSV export of whichever report was requested. */
    public static function export(): void
    {
        $p    = self::period();
        $what = (string) input('what', 'sales');
        $span = date('F j, Y', strtotime($p['from'])) . ' to ' . date('F j, Y', strtotime($p['to']));
        $who  = Auth::user()['full_name'] ?? '';
        $head = 'A&A SURPLUS - Household Appliances Trading, Panabo City';

        if ($what === 'movement') {
            $rows = Database::all(
                'SELECT m.created_at, p.sku, p.name, m.type, m.quantity, m.balance,
                        m.reference, m.notes, COALESCE(u.full_name,"System") staff
                 FROM stock_movements m JOIN products p ON p.id = m.product_id
                 LEFT JOIN users u ON u.id = m.user_id
                 WHERE DATE(m.created_at) BETWEEN ? AND ?
                 ORDER BY m.created_at', [$p['from'], $p['to']]);
            Audit::log('Reports', 'EXPORT', 'Exported the inventory movement report');
            export_table('AA_movement_' . $p['from'] . '_to_' . $p['to'], [
                'title'    => $head,
                'subtitle' => 'Stock Movement Ledger  |  ' . $span . '  |  prepared by ' . $who,
                'sheet'    => 'Stock Movement',
                'columns'  => [
                    ['label' => 'Date',        'type' => 'datetime'],
                    ['label' => 'SKU',         'type' => 'text', 'width' => 16],
                    ['label' => 'Item',        'type' => 'text', 'width' => 34],
                    ['label' => 'Type',        'type' => 'text', 'width' => 10],
                    ['label' => 'Quantity',    'type' => 'int'],
                    ['label' => 'Balance',     'type' => 'int'],
                    ['label' => 'Reference',   'type' => 'text', 'width' => 18],
                    ['label' => 'Notes',       'type' => 'text', 'width' => 32],
                    ['label' => 'Recorded by', 'type' => 'text', 'width' => 22],
                ],
                'rows' => $rows,
            ]);
        }

        if ($what === 'category') {
            $rows = [];
            foreach (Sale::dailyByCategory($p['from'], $p['to']) as $d) {
                $rows[] = [$d['day'], $d['category'], (int) $d['units'], (float) $d['revenue']];
            }
            Audit::log('Reports', 'EXPORT', 'Exported the daily sales-by-category report');
            export_table('AA_category_sales_' . $p['from'] . '_to_' . $p['to'], [
                'title'    => $head,
                'subtitle' => 'Daily Sales by Category  |  ' . $span . '  |  prepared by ' . $who,
                'sheet'    => 'Sales by Category',
                'columns'  => [
                    ['label' => 'Date',        'type' => 'date'],
                    ['label' => 'Category',    'type' => 'text', 'width' => 26],
                    ['label' => 'Pieces Sold', 'type' => 'int', 'width' => 13],
                    ['label' => 'Revenue',     'type' => 'money'],
                ],
                'rows'   => $rows,
                'totals' => ['TOTAL', null, 'sum', 'sum'],
            ]);
        }

        if ($what === 'products') {
            $rows = Database::all(
                'SELECT i.sku, i.product_name, SUM(i.quantity) units, SUM(i.line_total) revenue
                 FROM sale_items i JOIN sales s ON s.id = i.sale_id
                 WHERE DATE(s.sale_date) BETWEEN ? AND ?
                 GROUP BY i.sku, i.product_name ORDER BY revenue DESC', [$p['from'], $p['to']]);
            Audit::log('Reports', 'EXPORT', 'Exported the product sales report');
            export_table('AA_product_sales_' . $p['from'] . '_to_' . $p['to'], [
                'title'    => $head,
                'subtitle' => 'Sales by Item  |  ' . $span . '  |  prepared by ' . $who,
                'sheet'    => 'Sales by Item',
                'columns'  => [
                    ['label' => 'SKU',        'type' => 'text', 'width' => 16],
                    ['label' => 'Item',       'type' => 'text', 'width' => 38],
                    ['label' => 'Units Sold', 'type' => 'int', 'width' => 12],
                    ['label' => 'Revenue',    'type' => 'money'],
                ],
                'rows'   => $rows,
                'totals' => ['TOTAL', null, 'sum', 'sum'],
            ]);
        }

        $rows = Database::all(
            'SELECT s.invoice_no, s.sale_date, COALESCE(u.full_name,"-") cashier,
                    COALESCE(s.customer_name,"Walk-in") customer, s.payment_method,
                    s.subtotal, s.discount,
                    CASE WHEN s.discount > 0 THEN COALESCE(a.full_name,"not recorded")
                         ELSE "" END AS discount_approved_by,
                    s.total, s.amount_paid, s.change_due
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN users a ON a.id = s.discount_approved_by
             WHERE DATE(s.sale_date) BETWEEN ? AND ?
             ORDER BY s.sale_date', [$p['from'], $p['to']]);
        Audit::log('Reports', 'EXPORT', 'Exported the sales transactions report');
        export_table('AA_sales_' . $p['from'] . '_to_' . $p['to'], [
            'title'    => $head,
            'subtitle' => 'Sales Transactions  |  ' . $span . '  |  prepared by ' . $who,
            'sheet'    => 'Sales',
            'columns'  => [
                ['label' => 'Invoice',              'type' => 'text', 'width' => 17],
                ['label' => 'Date',                 'type' => 'datetime'],
                ['label' => 'Cashier',              'type' => 'text', 'width' => 22],
                ['label' => 'Customer',             'type' => 'text', 'width' => 22],
                ['label' => 'Payment',              'type' => 'text', 'width' => 15],
                ['label' => 'Subtotal',             'type' => 'money'],
                ['label' => 'Discount',             'type' => 'money'],
                ['label' => 'Discount Approved By', 'type' => 'text', 'width' => 22],
                ['label' => 'Total',                'type' => 'money'],
                ['label' => 'Paid',                 'type' => 'money'],
                ['label' => 'Change',               'type' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => ['TOTAL', null, null, null, null, 'sum', 'sum', null, 'sum', 'sum', 'sum'],
        ]);
    }
}
