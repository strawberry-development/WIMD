<?php

namespace Wimd\Model;

use Wimd\Metrics\MetricsCollector;

class DataMetric
{
    public int $total_records;
    public float $records_per_second;
    public string $overall_rating;
    public string $rating_color;
    public ?string $fastest_seeder;
    public float $max_records_per_second;
    public ?string $slowest_seeder;
    public float $min_records_per_second;
    public float $avg_operations_per_seeder;
    public int $seeders_count;
    public float $performance_variance;
    public array $performance_distribution;
    public float $total_time;
    public string $formatted_time;
    public float $time_per_record;
    public float $speed_variance;
    public array $seeders;
    public string $fastColor = "green";
    public string $slowColor = "red";

    /**
     * Constructor to create DataMetric from seeders and total time
     *
     * @param array $seeders
     * @param float $totalTime
     * @param MetricsCollector|null $metricsCollector
     */
    public function __construct(array $seeders, float $totalTime, ?MetricsCollector $metricsCollector = null)
    {
        // If no MetricsCollector is provided, create a default one
        if ($metricsCollector === null) {
            $metricsCollector = new MetricsCollector();
        }

        $this->seeders = $seeders;
        $this->total_time = $totalTime;

        // Calculate and populate all metrics
        $metrics = $this->calculateMetrics($seeders, $totalTime, $metricsCollector);

        foreach ($metrics as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Alternative constructor for manual hydration (for backward compatibility)
     *
     * @param array $attributes
     * @return static
     */
    public static function fromAttributes(array $attributes = []): self
    {
        $instance = new self([], 0.0);

        foreach ($attributes as $key => $value) {
            $instance->{$key} = $value;
        }

        return $instance;
    }

    /**
     * Calculate overall metrics from seeders
     *
     * @param array $seeders
     * @param float $totalTime
     * @param MetricsCollector $metricsCollector
     * @return array
     */
    protected function calculateMetrics(
        array $seeders,
        float $totalTime,
        MetricsCollector $metricsCollector
    ): array {
        $totalRecords = 0;
        $fastestSeeder = null;
        $slowestSeeder = null;
        $maxRecordsPerSecond = 0;
        $minRecordsPerSecond = PHP_FLOAT_MAX;

        foreach ($seeders as $seederClass => $seeder) {
            $metrics = $seeder['metrics'];

            if ($metrics->recordsAdded === 0) {
                continue;
            }

            $totalRecords += $metrics->recordsAdded;

            if ($metrics->recordsPerSecond > $maxRecordsPerSecond) {
                $maxRecordsPerSecond = $metrics->recordsPerSecond;
                $fastestSeeder = $metrics->getShortName();
            }

            if ($metrics->recordsPerSecond < $minRecordsPerSecond && $metrics->recordsPerSecond > 0) {
                $minRecordsPerSecond = $metrics->recordsPerSecond;
                $slowestSeeder = $metrics->getShortName();
            }
        }

        $recordsPerSecond = $totalTime > 0 ? round($totalRecords / $totalTime, 2) : 0;
        $overallRating = $metricsCollector->calculatePerformanceRating($recordsPerSecond, $totalRecords);

        // Use the instance method
        $ratingColor = $this->getRatingColor($overallRating);

        $avgOps = 0;
        $seedersCount = count(array_filter($seeders, function($seeder) {
            return $seeder['metrics']->recordsAdded > 0;
        }));

        if ($seedersCount > 0) {
            $avgOps = round($recordsPerSecond / $seedersCount, 2);
        }

        $variance = 0;
        $recordsPerSecondValues = [];

        foreach ($seeders as $seeder) {
            if ($seeder['metrics']->recordsPerSecond > 0) {
                $recordsPerSecondValues[] = $seeder['metrics']->recordsPerSecond;
            }
        }

        if (count($recordsPerSecondValues) > 1) {
            $mean = array_sum($recordsPerSecondValues) / count($recordsPerSecondValues);
            $variance = array_sum(array_map(function($x) use ($mean) {
                    return pow($x - $mean, 2);
                }, $recordsPerSecondValues)) / count($recordsPerSecondValues);
            $variance = round(sqrt($variance), 2);
        }

        $performanceDistribution = [
            'excellent' => 0,
            'good' => 0,
            'average' => 0,
            'poor' => 0,
            'critical' => 0
        ];

        foreach ($seeders as $seeder) {
            $metrics = $seeder['metrics'];
            if ($metrics->recordsAdded === 0) continue;

            $rating = $metricsCollector->calculatePerformanceRating(
                $metrics->recordsPerSecond,
                $metrics->recordsAdded
            );

            if ($rating >= 90) $performanceDistribution['excellent']++;
            elseif ($rating >= 70) $performanceDistribution['good']++;
            elseif ($rating >= 50) $performanceDistribution['average']++;
            elseif ($rating >= 30) $performanceDistribution['poor']++;
            else $performanceDistribution['critical']++;
        }

        $timePerRecord = $totalRecords > 0 ? round(($totalTime * 1000) / $totalRecords, 2) : 0;

        $speedVariance = 0;
        if ($minRecordsPerSecond > 0) {
            $speedVariance = round(($maxRecordsPerSecond / $minRecordsPerSecond) * 100 - 100, 1);
        }

        return [
            'total_records' => $totalRecords,
            'records_per_second' => $recordsPerSecond,
            'overall_rating' => $overallRating,
            'rating_color' => $ratingColor,
            'fastest_seeder' => $fastestSeeder,
            'max_records_per_second' => $maxRecordsPerSecond,
            'slowest_seeder' => $slowestSeeder,
            'min_records_per_second' => $minRecordsPerSecond,
            'avg_operations_per_seeder' => $avgOps,
            'seeders_count' => $seedersCount,
            'performance_variance' => $variance,
            'performance_distribution' => $performanceDistribution,
            'total_time' => $totalTime,
            'formatted_time' => $this->formatTime($totalTime),
            'time_per_record' => $timePerRecord,
            'speed_variance' => $speedVariance,
        ];
    }

    /**
     * Get the performance distribution as an array
     *
     * @return array
     */
    public function getPerformanceDistributionArray(): array
    {
        return is_array($this->performance_distribution)
            ? $this->performance_distribution
            : json_decode($this->performance_distribution, true);
    }

    /**
     * Format time in a human-readable way
     *
     * @param float $seconds
     * @return string
     */
    public function formatTime(float $seconds): string
    {
        if ($seconds < 0.1) {
            return round($seconds * 1000) . " ms";
        } elseif ($seconds < 60) {
            return round($seconds, 2) . " sec";
        } elseif ($seconds < 3600) {
            $mins = floor($seconds / 60);
            $secs = $seconds % 60;
            return "{$mins}m " . round($secs, 2) . "s";
        } else {
            $hours = floor($seconds / 3600);
            $mins = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            return "{$hours}h {$mins}m " . round($secs, 2) . "s";
        }
    }

    /**
     * Get rating color code for console output
     *
     * @param string $rating
     * @return string
     */
    public function getRatingColor(string $rating): string
    {
        switch ($rating) {
            case 'Good':
                return '+green';
            case 'Excellent':
                return 'green';
            case 'Average':
                return 'yellow';
            case 'Slow':
                return 'red';
            case 'Very Slow':
                return '+red';
            default:
                return 'white';
        }
    }
}
