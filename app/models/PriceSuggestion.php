<?php
/** Suggestions only. Normal save and price approval rules still decide changes. */
class PriceSuggestion
{
    public static function forItem(string $name, int $categoryId): array
    {
        $category = Category::find($categoryId);
        $rows = $category ? Database::all(
            'SELECT i.product_name, i.quantity, i.line_total, s.subtotal, s.total,
                    i.category_at_sale
             FROM sale_items i JOIN sales s ON s.id=i.sale_id
             LEFT JOIN products p ON p.id=i.product_id
             WHERE (i.category_at_sale=? OR (i.category_at_sale IS NULL AND p.category_id=?))
               AND i.quantity>0 AND i.line_total>0 AND s.subtotal>0 AND s.total>0
             ORDER BY s.sale_date DESC, i.id DESC LIMIT 200', [$category['name'], $categoryId]) : [];
        return self::fromRows($name, $rows);
    }

    public static function fromRows(string $name, array $rows): array
    {
        $tokens = self::tokens($name);
        $matches = [];
        foreach ($rows as $row) {
            $other = self::tokens((string) $row['product_name']);
            $shared = count(array_intersect($tokens, $other));
            if (!$shared) continue;
            $score = $shared / max(count($tokens), count($other), 1);
            if ($score < 0.35) continue;
            $quantity = (int) $row['quantity'];
            $subtotal = (float) $row['subtotal'];
            if ($quantity <= 0 || $subtotal <= 0) continue;
            // Allocate the sale's actual discount to the line, including older sales.
            $price = round((float) $row['line_total'] * ((float) $row['total'] / $subtotal) / $quantity, 2);
            if (!is_finite($price) || $price <= 0) continue;
            $matches[] = ['price' => $price, 'score' => $score, 'legacy' => $row['category_at_sale'] === null];
        }
        if ($matches) {
            $best = max(array_column($matches, 'score'));
            $matches = array_values(array_filter($matches, fn($m) => $m['score'] >= $best - 0.15));
            $prices = array_column($matches, 'price'); sort($prices, SORT_NUMERIC);
            $count = count($prices); $middle = intdiv($count, 2);
            $median = $count % 2 ? $prices[$middle] : ($prices[$middle-1] + $prices[$middle]) / 2;
            return ['amount' => round($median, 2), 'source' => 'sales_history', 'sample_count' => $count,
                'explanation' => 'Based on ' . $count . ' similar sales line(s), after discounts. Review the actual condition before using it.'
                    . (in_array(true, array_column($matches, 'legacy'), true) ? ' Some older sales use the item’s current category because its original category was not recorded.' : '')];
        }
        $config = require BASE_PATH . '/config/photo-ai.php';
        $amount = (float) ($config['demo_price'] ?? 500);
        return !empty($config['demo_pricing']) && is_finite($amount) && $amount > 0
            ? ['amount' => round($amount, 2), 'source' => 'demo', 'sample_count' => 0,
                'explanation' => 'Demo placeholder only — no matching sales history. Replace this price after inspecting the product.']
            : ['amount' => null, 'source' => 'none', 'sample_count' => 0,
                'explanation' => 'No matching sales history. Enter an initial price manually.'];
    }

    private static function tokens(string $name): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ignore = ['a','an','the','with','and','of','for','in','on','set','pcs','piece','pieces',
            'black','white','brown','red','blue','green','wooden','wood','metal','small','large','used','surplus'];
        return array_values(array_unique(array_filter($tokens, fn($t) => mb_strlen($t) > 2 && !in_array($t, $ignore, true))));
    }
}
