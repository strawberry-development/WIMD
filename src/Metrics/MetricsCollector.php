<?php
namespace Wimd\Metrics;

class MetricsCollector
{
    /**
     * Performance history for trending
     * @var array
     */
    protected array $performanceHistory = [];

    /**
     * Create metrics for a seeder
     *
     * @param string $seederClass
     * @param string $tableName
     * @return SeederMetrics
     */
    public function createSeederMetrics(string $seederClass, string $tableName): SeederMetrics
    {
        return new SeederMetrics($seederClass, $tableName);
    }

    /**
     * Update metrics for a seeder
     *
     * @param SeederMetrics $metrics
     * @param int $recordsAdded
     * @param float $executionTime
     * @return void
     */
    public function updateSeederMetrics(SeederMetrics $metrics, int $recordsAdded, float $executionTime): void
    {
        $metrics->update($recordsAdded, $executionTime);
        $metrics->rating = $this->calculatePerformanceRating($metrics->recordsPerSecond, $recordsAdded);

        // Track performance history for trending
        $this->trackPerformance($metrics->seederClass, $metrics->recordsPerSecond);
    }

    /**
     * Calculate performance rating based on records per second
     *
     * @param float $recordsPerSecond
     * @param int $recordsAdded
     * @return string
     */
    public function calculatePerformanceRating(float $recordsPerSecond, int $recordsAdded): string
    {
        // Handle case with no records added
        if ($recordsAdded === 0) {
            return 'N/A';
        }

        $thresholds = config('wimd.thresholds', [
            'excellent' => 1000,
            'good' => 500,
            'average' => 100,
            'slow' => 10
        ]);

        if ($recordsPerSecond > $thresholds['excellent']) {
            return 'Excellent';
        } else if ($recordsPerSecond > $thresholds['good']) {
            return 'Good';
        } else if ($recordsPerSecond > $thresholds['average']) {
            return 'Average';
        } else if ($recordsPerSecond > $thresholds['slow']) {
            return 'Slow';
        } else {
            return 'Very Slow';
        }
    }

    /**
     * Track performance for a seeder
     *
     * @param string $seederClass
     * @param float $recordsPerSecond
     * @return void
     */
    protected function trackPerformance(string $seederClass, float $recordsPerSecond): void
    {
        if (!isset($this->performanceHistory[$seederClass])) {
            $this->performanceHistory[$seederClass] = [];
        }

        $this->performanceHistory[$seederClass][] = $recordsPerSecond;
    }

    /**
     * Get performance history for a seeder
     *
     * @param string $seederClass
     * @return array
     */
    public function getPerformanceHistory(string $seederClass): array
    {
        return $this->performanceHistory[$seederClass] ?? [];
    }

    /**
     * Get performance trend for a seeder
     *
     * @param string $seederClass
     * @return string One of: 'up', 'down', 'stable', 'unknown'
     */
    public function getPerformanceTrend(string $seederClass): string
    {
        $history = $this->getPerformanceHistory($seederClass);

        if (count($history) < 2) {
            return 'unknown';
        }

        $first = $history[0];
        $last = end($history);

        $change = $last - $first;
        $percentChange = ($first > 0) ? ($change / $first) * 100 : 0;

        if (abs($percentChange) < 5) {
            return 'stable';
        }

        return $percentChange > 0 ? 'up' : 'down';
    }

    /**
     * Get the color for a performance trend
     *
     * @param string $trend
     * @return string
     */
    public function getTrendColor(string $trend): string
    {
        switch ($trend) {
            case 'up':
                return '<fg=green>';
            case 'down':
                return '<fg=red>';
            case 'stable':
                return '<fg=blue>';
            default:
                return '<fg=white>';
        }
    }

    /**
     * Get trend icon for a performance trend
     *
     * @param string $trend
     * @param bool $useUnicode
     * @return string
     */
    public function getTrendIcon(string $trend, bool $useUnicode = true): string
    {
        if ($useUnicode) {
            switch ($trend) {
                case 'up':
                    return '↑';
                case 'down':
                    return '↓';
                case 'stable':
                    return '→';
                default:
                    return '?';
            }
        } else {
            switch ($trend) {
                case 'up':
                    return '+';
                case 'down':
                    return '-';
                case 'stable':
                    return '=';
                default:
                    return '?';
            }
        }
    }

    /**
     * Calculate anomaly score (how far from normal is this performance)
     *
     * @param float $value
     * @param array $history
     * @return float
     */
    public function calculateAnomalyScore(float $value, array $history): float
    {
        if (empty($history)) {
            return 0;
        }

        // Calculate mean and standard deviation
        $mean = array_sum($history) / count($history);

        if (count($history) < 2) {
            return abs($value - $mean) / max(1, $mean);
        }

        $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $history)) / count($history);

        $stdDev = sqrt($variance);

        // Calculate z-score
        if ($stdDev > 0) {
            return abs(($value - $mean) / $stdDev);
        }

        return 0;
    }

    public function getPerformanceSummary(array $seeders): array
    {
        $summary = [
            'total_records' => 0,
            'total_time' => 0,
            'averages' => [
                'records_per_second' => 0,
                'time_per_record' => 0,
            ],
            'distribution' => [
                'Excellent' => 0,
                'Good' => 0,
                'Average' => 0,
                'Slow' => 0,
                'Very Slow' => 0,
                'N/A' => 0,
            ],
        ];

        $validSeeders = 0;

        foreach ($seeders as $seeder) {
            $metrics = $seeder['metrics'];

            if ($metrics->recordsAdded > 0) {
                $summary['total_records'] += $metrics->recordsAdded;
                $summary['total_time'] += $metrics->executionTime;
                $validSeeders++;
            }

            // Add to rating distribution
            if (isset($summary['distribution'][$metrics->rating])) {
                $summary['distribution'][$metrics->rating]++;
            }
        }

        // Calculate averages if there are valid seeders
        if ($validSeeders > 0) {
            $summary['averages']['records_per_second'] = $summary['total_time'] > 0 ?
                round($summary['total_records'] / $summary['total_time'], 2) : 0;

            $summary['averages']['time_per_record'] = $summary['total_records'] > 0 ?
                round(($summary['total_time'] * 1000) / $summary['total_records'], 2) : 0;
        }

        return $summary;
    }
}
