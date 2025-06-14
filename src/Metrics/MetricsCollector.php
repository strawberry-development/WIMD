<?php
namespace Wimd\Metrics;

/**
 * Central entry point for collecting and managing seeder performance metrics.
 */
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
}
