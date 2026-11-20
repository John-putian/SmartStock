<?php
/** FR-06 - stock and alert notification centre. */
class AlertController
{
    public static function index(): void
    {
        $alerts = Product::alerts();

        $out = $low = [];
        foreach ($alerts as $a) {
            if ((int) $a['quantity'] <= 0) $out[] = $a; else $low[] = $a;
        }

        // Fast movers that are still healthy but will need attention soon.
        $watch = Database::all(
            'SELECT p.*, c.name AS category_name, SUM(i.quantity) sold_30
             FROM sale_items i
             JOIN sales s ON s.id = i.sale_id
             JOIN products p ON p.id = i.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND p.status = "active" AND p.quantity > p.reorder_level
             GROUP BY p.id
             HAVING p.quantity <= sold_30
             ORDER BY sold_30 DESC LIMIT 12');

        view('alerts/index', [
            'out' => $out, 'low' => $low, 'watch' => $watch,
            'counts' => Product::counts(),
        ], 'Stock Alerts');
    }
}
