<?php
/** FR-09 - decision support: sales analytics. */
class AnalyticsController
{
    /** Sales analytics dashboard. */
    public static function index(): void
    {
        $to   = date('Y-m-d');
        $from = (string) input('from', date('Y-m-d', strtotime('-179 days')));

        $monthly = Sale::trend($from, $to, 'month');
        $weekly  = Sale::trend(date('Y-m-d', strtotime('-83 days')), $to, 'week');

        // Day-of-week performance helps staffing and delivery scheduling.
        $dow = Database::all(
            'SELECT DAYOFWEEK(sale_date) d, DAYNAME(sale_date) dname,
                    COUNT(*) txn, COALESCE(SUM(total),0) revenue
             FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
             GROUP BY d, dname ORDER BY d', [$from, $to]);

        // Slow movers: in stock but nothing sold in 60 days - capital sitting idle.
        $slow = Database::all(
            'SELECT p.*, c.name AS category_name,
                    (SELECT MAX(s.sale_date) FROM sale_items i JOIN sales s ON s.id = i.sale_id
                     WHERE i.product_id = p.id) AS last_sold
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = "active" AND p.quantity > 0
               AND p.id NOT IN (
                 SELECT i.product_id FROM sale_items i JOIN sales s ON s.id = i.sale_id
                 WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND i.product_id IS NOT NULL)
             ORDER BY (p.quantity * p.cost_price) DESC LIMIT 12');

        view('analytics/index', [
            'from'    => $from, 'to' => $to,
            'summary' => Sale::summary($from, $to),
            'monthly' => $monthly,
            'weekly'  => $weekly,
            'byCat'   => Sale::byCategory($from, $to),
            'byHour'  => Sale::byHour($from, $to),
            'dow'     => $dow,
            'top'     => Sale::topProducts($from, $to, 10),
            'slow'    => $slow,
            'proj'    => Forecast::revenueProjection(8, 3),
        ], 'Sales Analytics');
    }

}
