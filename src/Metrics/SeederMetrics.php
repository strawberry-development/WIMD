<?php
namespace Wimd\Metrics;

use Illuminate\Support\Facades\Config;

class SeederMetrics
{
    /**
     * Seeder class name
     * @var string
     */
    public string $seederClass;

    /**
     * Table name
     * @var string
     */
    public string $tableName;

    /**
     * Records added count
     * @var int
     */
    public int $recordsAdded = 0;

    /**
     * Execution time in seconds
     * @var float
     */
    public float $executionTime = 0.0;

    /**
     * Records per second
     * @var float
     */
    public float $recordsPerSecond = 0.0;

    /**
     * Performance rating
     * @var string
     */
    public string $rating = 'N/A';

    /**
     * Start time
     * @var float
     */
    public float $startTime;

    /**
     * End time
     * @var float|null
     */
    public ?float $endTime = null;

    /**
     * Memory usage at start
     * @var int
     */
    public int $startMemory;

    /**
     * Memory usage at end
     * @var int|null
     */
    public ?int $endMemory = null;

    /**
     * Memory usage difference
     * @var int
     */
    public int $memoryUsage = 0;

    /**
     * Records added over time (for tracking)
     * @var array
     */
    protected array $recordsOverTime = [];

    /**
     * Batch sizes (if available)
     * @var array
     */
    protected array $batchSizes = [];

    /**
     * Memory warnings
     * @var array
     */
    protected array $memoryWarnings = [];

    /**
     * Memory usage over time (if tracking enabled)
     * @var array
     */
    protected array $memoryOverTime = [];

    /**
     * Constructor
     *
     * @param string $seederClass
     * @param string $tableName
     */
    public function __construct(string $seederClass, string $tableName)
    {
        $this->seederClass = $seederClass;
        $this->tableName = $tableName;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        // Initialize memory tracking if enabled
        if (Config::get('wind.memory.options.track_usage_over_time', false)) {
            $this->trackMemoryUsage();
        }
    }

    /**
     * Get seeder short name without namespace
     *
     * @return string
     */
    public function getShortName(): string
    {
        $parts = explode('\\', $this->seederClass);
        return end($parts);
    }

    /**
     * Calculate records per second
     *
     * @return float
     */
    public function calculateRecordsPerSecond(): float
    {
        if ($this->executionTime > 0 && $this->recordsAdded > 0) {
            $this->recordsPerSecond = round($this->recordsAdded / $this->executionTime, 2);
        }
        return $this->recordsPerSecond;
    }

    /**
     * Update metrics with new data
     *
     * @param int $recordsAdded
     * @param float $executionTime
     * @return void
     */
    public function update(int $recordsAdded, float $executionTime): void
    {
        $this->recordsAdded = $recordsAdded;
        $this->executionTime = $executionTime;
        $this->endTime = microtime(true);
        $this->endMemory = memory_get_usage();
        $this->memoryUsage = $this->endMemory - $this->startMemory;
        $this->calculateRecordsPerSecond();

        // Track records for this checkpoint
        $this->recordsOverTime[] = [
            'time' => $this->endTime,
            'records' => $this->recordsAdded,
            'elapsed' => $this->executionTime
        ];

        // Track memory usage if enabled
        if (Config::get('wind.memory.options.track_usage_over_time', false)) {
            $this->trackMemoryUsage();
        }

        // Check memory warnings
        $this->checkMemoryWarnings();
    }

    /**
     * Track a batch of records inserted
     *
     * @param int $batchSize
     * @param float $batchTime
     * @return void
     */
    public function trackBatch(int $batchSize, float $batchTime): void
    {
        $this->batchSizes[] = [
            'size' => $batchSize,
            'time' => $batchTime,
            'timestamp' => microtime(true)
        ];

        // Check memory warnings after each batch if display during seeding is enabled
        if (Config::get('wind.memory.options.display_during_seeding', true)) {
            $this->checkMemoryWarnings();
        }
    }

    /**
     * Track current memory usage
     *
     * @return void
     */
    public function trackMemoryUsage(): void
    {
        $currentMemory = memory_get_usage();
        $peak = memory_get_peak_usage();

        $this->memoryOverTime[] = [
            'timestamp' => microtime(true),
            'usage' => $currentMemory,
            'peak' => $peak,
            'records' => $this->recordsAdded
        ];
    }

    /**
     * Check memory usage against configured thresholds
     *
     * @return array|null Warning info or null if no warning
     */
    public function checkMemoryWarnings(): ?array
    {
        $warning = MemoryWarnings::checkMemoryUsage($this);

        if ($warning) {
            $this->memoryWarnings[] = $warning;

            // Handle abort case if needed
            if ($warning['abort']) {
                // This will typically be caught by the seeder command
                throw new \RuntimeException("Seeding aborted: {$warning['message']}");
            }
        }

        return $warning;
    }

    /**
     * Get memory warnings
     *
     * @return array
     */
    public function getMemoryWarnings(): array
    {
        return $this->memoryWarnings;
    }

    /**
     * Get average batch size (if available)
     *
     * @return float|null
     */
    public function getAverageBatchSize(): ?float
    {
        if (empty($this->batchSizes)) {
            return null;
        }

        $total = array_sum(array_column($this->batchSizes, 'size'));
        return round($total / count($this->batchSizes), 2);
    }

    /**
     * Get average batch time (if available)
     *
     * @return float|null
     */
    public function getAverageBatchTime(): ?float
    {
        if (empty($this->batchSizes)) {
            return null;
        }

        $total = array_sum(array_column($this->batchSizes, 'time'));
        return round($total / count($this->batchSizes), 4);
    }

    /**
     * Get memory usage per record
     *
     * @return float
     */
    public function getMemoryPerRecord(): float
    {
        if ($this->recordsAdded <= 0) {
            return 0;
        }

        return round($this->memoryUsage / $this->recordsAdded, 2);
    }

    /**
     * Get memory usage in a human-readable format
     *
     * @return string
     */
    public function getFormattedMemoryUsage(): string
    {
        $bytes = abs($this->memoryUsage);

        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }

    /**
     * Get current memory usage in a human-readable format
     *
     * @return string
     */
    public function getCurrentMemoryUsage(): string
    {
        $bytes = memory_get_usage();

        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }

    /**
     * Get memory rating based on per-record usage
     *
     * @return string
     */
    public function getMemoryRating(): string
    {
        if ($this->recordsAdded <= 0) {
            return 'N/A';
        }

        $memoryPerRecordKB = $this->getMemoryPerRecord() / 1024;
        $thresholds = [
            'efficient' => Config::get('wind.memory.per_record.efficient', 1),
            'acceptable' => Config::get('wind.memory.per_record.acceptable', 5),
            'concerning' => Config::get('wind.memory.per_record.concerning', 20),
            'excessive' => Config::get('wind.memory.per_record.excessive', 50),
        ];

        if ($memoryPerRecordKB <= $thresholds['efficient']) {
            return 'Efficient';
        } elseif ($memoryPerRecordKB <= $thresholds['acceptable']) {
            return 'Acceptable';
        } elseif ($memoryPerRecordKB <= $thresholds['concerning']) {
            return 'Concerning';
        } elseif ($memoryPerRecordKB <= $thresholds['excessive']) {
            return 'Excessive';
        } else {
            return 'Critical';
        }
    }

    /**
     * Get the time per record in milliseconds
     *
     * @return float
     */
    public function getTimePerRecord(): float
    {
        if ($this->recordsAdded <= 0) {
            return 0;
        }

        return round(($this->executionTime * 1000) / $this->recordsAdded, 2);
    }

    /**
     * Check if the seeder has performance data
     *
     * @return bool
     */
    public function hasPerformanceData(): bool
    {
        return $this->recordsAdded > 0 && $this->executionTime > 0;
    }

    /**
     * Get records over time data for velocity calculation
     *
     * @return array
     */
    public function getRecordsOverTime(): array
    {
        return $this->recordsOverTime;
    }

    /**
     * Get memory tracking data
     *
     * @return array
     */
    public function getMemoryOverTime(): array
    {
        return $this->memoryOverTime;
    }

    /**
     * Calculate if the seeder is improving over time
     *
     * @return string 'improving', 'degrading', 'stable', or 'unknown'
     */
    public function getPerformanceTrend(): string
    {
        if (count($this->recordsOverTime) < 2) {
            return 'unknown';
        }

        // Calculate speeds over multiple checkpoints
        $speeds = [];
        $previous = null;

        foreach ($this->recordsOverTime as $checkpoint) {
            if ($previous) {
                $timeDiff = $checkpoint['elapsed'] - $previous['elapsed'];
                $recordsDiff = $checkpoint['records'] - $previous['records'];

                if ($timeDiff > 0) {
                    $speeds[] = $recordsDiff / $timeDiff;
                }
            }

            $previous = $checkpoint;
        }

        if (count($speeds) < 2) {
            return 'unknown';
        }

        // Compare first and last speed
        $firstSpeed = $speeds[0];
        $lastSpeed = end($speeds);

        $difference = ($lastSpeed - $firstSpeed) / $firstSpeed * 100;

        if (abs($difference) < 5) {
            return 'stable';
        }

        return $difference > 0 ? 'improving' : 'degrading';
    }
}
