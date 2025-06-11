<?php

namespace Wimd\Model;

use Wimd\Config\RenderingConfig;
use Wimd\Facades\Wimd;
use Wimd\Metrics\MetricsCollector;

class DataMetric
{
    public int $total_records = 0;
    public float $records_per_second = 0.0;
    public string $overall_rating = '';
    public string $rating_color = '';
    public ?string $fastest_seeder = null;
    public float $max_records_per_second = 0.0;
    public ?string $slowest_seeder = null;
    public float $min_records_per_second = 0.0;
    public float $avg_operations_per_seeder = 0.0;
    public int $seeders_count = 0;
    public float $performance_variance = 0.0;
    public float $total_time = 0.0;
    public string $formatted_time = '';
    public float $time_per_record = 0.0;
    public float $speed_variance = 0.0;
    public array $seeders = [];
    public string $fastColor = "green";
    public string $slowColor = "red";

    protected RenderingConfig $config;
    protected MetricsCollector $metricsCollector;
    protected float $startTime;
    private bool $isStarted = false;

    /**
     * Constructor initializes the metric tracking
     */
    public function __construct(RenderingConfig $config = null)
    {
        $this->metricsCollector = new MetricsCollector();
        $this->config = $config ?? Wimd::getConfigInstance();
        $this->startTime = microtime(true);
    }

    /**
     * Start or restart metric collection
     */
    public function start(): self
    {
        $this->startTime = microtime(true);
        $this->isStarted = true;
        $this->resetMetrics();
        return $this;
    }

    /**
     * Add a seeder result to the metrics
     */
    public function addSeederResult(string $seederClass, int $recordsAdded, float $executionTime, string $tableName = null): self
    {
        if (!$this->isStarted) {
            $this->start();
        }

        // Create seeder metrics if not exists
        if (!isset($this->seeders[$seederClass])) {
            $this->seeders[$seederClass] = [
                'table' => $tableName ?? $this->getTableNameFromSeeder($seederClass),
                'metrics' => $this->metricsCollector->createSeederMetrics($seederClass, $tableName ?? $this->getTableNameFromSeeder($seederClass)),
            ];
        }

        // Update the seeder metrics
        $this->metricsCollector->updateSeederMetrics(
            $this->seeders[$seederClass]['metrics'],
            $recordsAdded,
            $executionTime
        );

        // Recalculate overall metrics
        $this->recalculateMetrics();

        return $this;
    }

    /**
     * Update a specific metric value directly
     */
    public function updateMetric(string $metricName, $value): self
    {
        if (property_exists($this, $metricName)) {
            $this->{$metricName} = $value;
        }
        return $this;
    }

    /**
     * Bulk update multiple metrics at once
     */
    public function updateMetrics(array $metrics): self
    {
        foreach ($metrics as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        return $this;
    }

    /**
     * Add records to total and recalculate
     */
    public function addRecords(int $records): self
    {
        $this->total_records += $records;
        $this->recalculateMetrics();
        return $this;
    }

    /**
     * Update the total time and recalculate dependent metrics
     */
    public function updateTotalTime(float $time): self
    {
        $this->total_time = $time;
        $this->formatted_time = $this->formatTime($time);
        $this->recalculateMetrics();
        return $this;
    }

    /**
     * End the metric collection and finalize calculations
     */
    public function end(): self
    {
        if ($this->isStarted) {
            $this->total_time = microtime(true) - $this->startTime;
            $this->formatted_time = $this->formatTime($this->total_time);
            $this->recalculateMetrics();
            $this->isStarted = false;
        }
        return $this;
    }

    /**
     * Recalculate all derived metrics based on current data
     */
    protected function recalculateMetrics(): void
    {
        if (empty($this->seeders)) {
            return;
        }

        $totalRecords = 0;
        $fastestSeeder = null;
        $slowestSeeder = null;
        $maxRecordsPerSecond = 0;
        $minRecordsPerSecond = PHP_FLOAT_MAX;
        $recordsPerSecondValues = [];

        foreach ($this->seeders as $seederClass => $seeder) {
            $metrics = $seeder['metrics'];

            if ($metrics->recordsAdded === 0) {
                continue;
            }

            $totalRecords += $metrics->recordsAdded;
            $recordsPerSecondValues[] = $metrics->recordsPerSecond;

            if ($metrics->recordsPerSecond > $maxRecordsPerSecond) {
                $maxRecordsPerSecond = $metrics->recordsPerSecond;
                $fastestSeeder = $metrics->getShortName();
            }

            if ($metrics->recordsPerSecond < $minRecordsPerSecond && $metrics->recordsPerSecond > 0) {
                $minRecordsPerSecond = $metrics->recordsPerSecond;
                $slowestSeeder = $metrics->getShortName();
            }
        }

        // Update totals
        $this->total_records = $totalRecords;
        $this->records_per_second = $this->total_time > 0 ? round($totalRecords / $this->total_time, 2) : 0;
        $this->overall_rating = $this->metricsCollector->calculatePerformanceRating($this->records_per_second, $totalRecords);
        $this->rating_color = $this->getRatingColor($this->overall_rating);

        // Update seeder-specific metrics
        $this->fastest_seeder = $fastestSeeder;
        $this->max_records_per_second = $maxRecordsPerSecond;
        $this->slowest_seeder = $slowestSeeder;
        $this->min_records_per_second = $minRecordsPerSecond === PHP_FLOAT_MAX ? 0 : $minRecordsPerSecond;

        // Calculate averages and variances
        $this->seeders_count = count(array_filter($this->seeders, function($seeder) {
            return $seeder['metrics']->recordsAdded > 0;
        }));

        $this->avg_operations_per_seeder = $this->seeders_count > 0
            ? round($this->records_per_second / $this->seeders_count, 2)
            : 0;

        // Calculate performance variance
        if (count($recordsPerSecondValues) > 1) {
            $mean = array_sum($recordsPerSecondValues) / count($recordsPerSecondValues);
            $variance = array_sum(array_map(function($x) use ($mean) {
                    return pow($x - $mean, 2);
                }, $recordsPerSecondValues)) / count($recordsPerSecondValues);
            $this->performance_variance = round(sqrt($variance), 2);
        } else {
            $this->performance_variance = 0;
        }

        // Calculate time per record
        $this->time_per_record = $totalRecords > 0
            ? round(($this->total_time * 1000) / $totalRecords, 2)
            : 0;

        // Calculate speed variance
        $this->speed_variance = ($this->min_records_per_second > 0)
            ? round(($maxRecordsPerSecond / $this->min_records_per_second) * 100 - 100, 1)
            : 0;
    }

    /**
     * Reset all metrics to initial state
     */
    public function resetMetrics(): self
    {
        $this->total_records = 0;
        $this->records_per_second = 0.0;
        $this->overall_rating = '';
        $this->rating_color = '';
        $this->fastest_seeder = null;
        $this->max_records_per_second = 0.0;
        $this->slowest_seeder = null;
        $this->min_records_per_second = 0.0;
        $this->avg_operations_per_seeder = 0.0;
        $this->seeders_count = 0;
        $this->performance_variance = 0.0;
        $this->total_time = 0.0;
        $this->formatted_time = '';
        $this->time_per_record = 0.0;
        $this->speed_variance = 0.0;
        $this->seeders = [];

        return $this;
    }

    /**
     * Alternative constructor for manual hydration (for backward compatibility)
     */
    public static function fromAttributes(array $attributes = []): self
    {
        $instance = new self();
        $instance->updateMetrics($attributes);
        return $instance;
    }

    /**
     * Get current metrics as array
     */
    public function toArray(): array
    {
        return [
            'total_records' => $this->total_records,
            'records_per_second' => $this->records_per_second,
            'overall_rating' => $this->overall_rating,
            'rating_color' => $this->rating_color,
            'fastest_seeder' => $this->fastest_seeder,
            'max_records_per_second' => $this->max_records_per_second,
            'slowest_seeder' => $this->slowest_seeder,
            'min_records_per_second' => $this->min_records_per_second,
            'avg_operations_per_seeder' => $this->avg_operations_per_seeder,
            'seeders_count' => $this->seeders_count,
            'performance_variance' => $this->performance_variance,
            'total_time' => $this->total_time,
            'formatted_time' => $this->formatted_time,
            'time_per_record' => $this->time_per_record,
            'speed_variance' => $this->speed_variance,
            'seeders' => $this->seeders,
        ];
    }

    /**
     * Check if metrics collection is currently active
     */
    public function isActive(): bool
    {
        return $this->isStarted;
    }

    /**
     * Get elapsed time since start
     */
    public function getElapsedTime(): float
    {
        return $this->isStarted ? microtime(true) - $this->startTime : $this->total_time;
    }

    /**
     * Format time in a human-readable way
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

    /**
     * Get table name from seeder class name
     */
    protected function getTableNameFromSeeder(string $seederName): string
    {
        $classNameParts = explode('\\', $seederName);
        $className = end($classNameParts);

        $tableName = preg_replace('/Seeder$/', '', $className);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tableName));
    }
}
