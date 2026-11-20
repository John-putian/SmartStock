<?php
/** Role-based dashboard - KPI summary, trend charts and quick navigation. */
class DashboardController
{
    public static function index(): void
    {
        // The shop floor gets a floor-shaped dashboard. A cashier watching the
        // door does not need the month's takings, the growth rate or the
        // category mix - those live under Insights, which is not theirs.
        if (!Auth::canManage()) {
            self::floorView();
            return;
        }

        $today     = date('Y-m-d');
        $monthFrom = date('Y-m-01');
        $last30    = date('Y-m-d', strtotime('-29 days'));

        $monthStats = Sale::summary($monthFrom, $today);

        // Same span of the previous month, for a like-for-like comparison.
        $prevStats = Sale::summary(date('Y-m-01', strtotime('-1 month')),
                                   date('Y-m-d', strtotime('-1 month')));
        $growth = ((float) ($prevStats['revenue'] ?? 0)) > 0
            ? ((float) $monthStats['revenue'] - (float) $prevStats['revenue'])
              / (float) $prevStats['revenue'] * 100
            : null;

        $alerts = Product::alerts();
        $recent = Sale::history(date('Y-m-d', strtotime('-14 days')), $today);

        view('dashboard/index', [
            'counts'      => Product::counts(),
            'todayStats'  => Sale::summary($today, $today),
            'monthStats'  => $monthStats,
            'growth'      => $growth,
            'trend'       => Sale::trend($last30, $today, 'day'),
            'topProducts' => Sale::topProducts($monthFrom, $today, 6),
            'categories'  => Sale::byCategory($monthFrom, $today),
            'alerts'      => array_slice($alerts, 0, 8),
            'alertTotal'  => count($alerts),
            'recent'      => array_slice($recent, 0, 8),
        ], 'Dashboard');
    }

    /** Cashier and work-staff dashboard: stock in, stock out, and my own till. */
    private static function floorView(): void
    {
        $today  = date('Y-m-d');
        $alerts = Product::alerts();

        view('dashboard/floor', [
            'counts'    => Product::counts(),
            'mine'      => Sale::summaryForUser((int) Auth::id(), $today),
            'soldToday' => Sale::piecesSoldOn($today),
            'movements' => Product::recentMovements(14),
            'alerts'    => array_slice($alerts, 0, 8),
            'alertTotal' => count($alerts),
            'myRequests' => PriceRequest::listing('', (int) Auth::id(), 5),
        ], 'Dashboard');
    }
}
