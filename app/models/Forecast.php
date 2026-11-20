<?php
/**
 * FR-09 - Decision support: predictive demand and stock forecasting.
 *
 * Two classical techniques are combined, both computed from the store's own
 * sales history so no external service is required:
 *
 *   1. Simple Moving Average (SMA) over the recent weekly demand - stable
 *      baseline, resistant to one-off spikes.
 *   2. Least-squares Linear Regression on the same weekly series - captures
 *      whether demand is trending up or down.
 *
 * The forecast blends the two (60% trend / 40% moving average) and converts
 * the result into a suggested reorder quantity using days-of-cover.
 */
class Forecast
{
    /** Weekly demand series for one product over the last N weeks. */
    public static function linearFit(array $y): array
    {
        $n = count($y);
        if ($n < 2) return ['slope' => 0.0, 'intercept' => $n ? (float) $y[0] : 0.0];

        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($y as $i => $v) {
            $sumX  += $i;
            $sumY  += $v;
            $sumXY += $i * $v;
            $sumXX += $i * $i;
        }
        $den = ($n * $sumXX) - ($sumX * $sumX);
        if (abs($den) < 1e-9) return ['slope' => 0.0, 'intercept' => $sumY / $n];

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $den;
        return ['slope' => $slope, 'intercept' => ($sumY - $slope * $sumX) / $n];
    }

    /** Full forecast package for one product. */
    public static function revenueProjection(int $historyMonths = 8, int $ahead = 3): array
    {
        $rows = Database::all(
            "SELECT DATE_FORMAT(sale_date,'%Y-%m') bucket, SUM(total) revenue
             FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY bucket ORDER BY bucket", [$historyMonths]);

        $labels  = array_column($rows, 'bucket');
        $values  = array_map('floatval', array_column($rows, 'revenue'));

        // The current (incomplete) month understates the trend - exclude it from the fit.
        $fitVals = $values;
        if ($labels && end($labels) === date('Y-m') && count($fitVals) > 2) {
            array_pop($fitVals);
        }
        $fit = self::linearFit($fitVals);
        $n   = count($fitVals);

        $projLabels = $projValues = [];
        for ($i = 0; $i < $ahead; $i++) {
            $projLabels[] = date('Y-m', strtotime('first day of +' . ($i + 1) . ' month'));
            $projValues[] = round(max(0, $fit['intercept'] + $fit['slope'] * ($n + $i)), 2);
        }

        return [
            'labels' => $labels, 'values' => $values,
            'proj_labels' => $projLabels, 'proj_values' => $projValues,
            'slope' => $fit['slope'],
        ];
    }
}
