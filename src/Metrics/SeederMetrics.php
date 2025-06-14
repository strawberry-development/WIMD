<?php
namespace Wimd\Metrics;

use Illuminate\Support\Facades\DB;

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
     * Memory usage over time (if tracking enabled)
     * @var array
     */
    protected array $memoryOverTime = [];

    // === NEW DATABASE-LEVEL METRICS ===

    /**
     * Database query count
     * @var int
     */
    public int $queryCount = 0;

    /**
     * Query execution times
     * @var array
     */
    protected array $queryTimes = [];

    /**
     * Connection pool metrics
     * @var array
     */
    protected array $connectionMetrics = [];

    /**
     * Lock wait times
     * @var array
     */
    protected array $lockWaitTimes = [];

    /**
     * Transaction count
     * @var int
     */
    public int $transactionCount = 0;

    /**
     * Index usage statistics
     * @var array
     */
    protected array $indexUsage = [];

    // === DATA QUALITY METRICS ===

    /**
     * Duplicate records encountered
     * @var int
     */
    public int $duplicatesFound = 0;

    /**
     * Validation failures
     * @var int
     */
    public int $validationFailures = 0;

    /**
     * Data transformation time
     * @var float
     */
    public float $transformationTime = 0.0;

    /**
     * Foreign key violations
     * @var int
     */
    public int $foreignKeyViolations = 0;

    /**
     * Null/empty value counts
     * @var array
     */
    protected array $nullValueCounts = [];

    /**
     * Validation error details
     * @var array
     */
    protected array $validationErrors = [];

    // === SYSTEM RESOURCE METRICS ===

    /**
     * CPU usage tracking
     * @var array
     */
    protected array $cpuUsage = [];

    /**
     * Network usage metrics
     * @var array
     */
    protected array $networkUsage = [];

    /**
     * Temporary file usage
     * @var array
     */
    protected array $tempFileUsage = [];

    // === ADVANCED PERFORMANCE METRICS ===

    /**
     * Throughput measurements for variance calculation
     * @var array
     */
    protected array $throughputMeasurements = [];

    /**
     * Connection timeout count
     * @var int
     */
    public int $connectionTimeouts = 0;

    /**
     * Retry attempts
     * @var int
     */
    public int $retryAttempts = 0;

    /**
     * Chunk processing efficiency
     * @var array
     */
    protected array $chunkEfficiency = [];

    // === BUSINESS LOGIC METRICS ===

    /**
     * Relationship creation count
     * @var int
     */
    public int $relationshipsCreated = 0;

    /**
     * File processing metrics
     * @var array
     */
    protected array $fileProcessing = [];

    /**
     * External API call metrics
     * @var array
     */
    protected array $apiCalls = [];

    /**
     * Cache metrics
     * @var array
     */
    protected array $cacheMetrics = ['hits' => 0, 'misses' => 0];

    /**
     * Peak memory usage
     * @var int
     */
    public int $peakMemoryUsage = 0;

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
        $this->peakMemoryUsage = memory_get_peak_usage();

        // Initialize tracking
        $this->initializeTracking();
    }

    /**
     * Initialize various tracking mechanisms
     *
     * @return void
     */
    protected function initializeTracking(): void
    {
            DB::enableQueryLog();
            $this->trackMemoryUsage();
            $this->initializeSystemTracking();
    }

    /**
     * Initialize system resource tracking
     *
     * @return void
     */
    protected function initializeSystemTracking(): void
    {
        // Track initial system state
        $this->cpuUsage[] = [
            'timestamp' => microtime(true),
            'usage' => $this->getCpuUsage()
        ];
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
        $this->peakMemoryUsage = max($this->peakMemoryUsage, memory_get_peak_usage());
        $this->calculateRecordsPerSecond();

        // Track records for this checkpoint
        $this->recordsOverTime[] = [
            'time' => $this->endTime,
            'records' => $this->recordsAdded,
            'elapsed' => $this->executionTime
        ];

        // Update throughput measurements
        $this->throughputMeasurements[] = $this->recordsPerSecond;

        // Update database metrics
        $this->updateDatabaseMetrics();

        // Update system resource metrics
        $this->updateSystemMetrics();
    }

    /**
     * Update database-specific metrics
     *
     * @return void
     */
    protected function updateDatabaseMetrics(): void
    {
            $queries = DB::getQueryLog();
            $this->queryCount = count($queries);

            foreach ($queries as $query) {
                $this->queryTimes[] = $query['time'] ?? 0;
            }

        // Track connection metrics
        $this->connectionMetrics[] = [
            'timestamp' => microtime(true),
            'active_connections' => $this->getActiveConnections(),
            'max_connections' => $this->getMaxConnections()
        ];
    }

    /**
     * Update system resource metrics
     *
     * @return void
     */
    protected function updateSystemMetrics(): void
    {
            $this->cpuUsage[] = [
                'timestamp' => microtime(true),
                'usage' => $this->getCpuUsage()
            ];
    }

    /**
     * Track a batch of records inserted
     *
     * @param int $batchSize
     * @param float $batchTime
     * @param array $additionalData
     * @return void
     */
    public function trackBatch(int $batchSize, float $batchTime, array $additionalData = []): void
    {
        $batchData = [
            'size' => $batchSize,
            'time' => $batchTime,
            'timestamp' => microtime(true),
            'efficiency' => $batchSize / $batchTime,
            'memory_usage' => memory_get_usage(),
            'duplicates' => $additionalData['duplicates'] ?? 0,
            'validation_failures' => $additionalData['validation_failures'] ?? 0
        ];

        $this->batchSizes[] = $batchData;

        // Track chunk efficiency
        $this->chunkEfficiency[] = [
            'size' => $batchSize,
            'records_per_second' => $batchSize / $batchTime,
            'memory_per_record' => memory_get_usage() / $batchSize
        ];
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

        // Check for potential memory leaks
        if (count($this->memoryOverTime) > 1) {
            $this->detectMemoryLeaks();
        }
    }

    /**
     * Detect potential memory leaks
     *
     * @return bool
     */
    protected function detectMemoryLeaks(): bool
    {
        if (count($this->memoryOverTime) < 5) {
            return false;
        }

        $recent = array_slice($this->memoryOverTime, -5);
        $trend = 0;

        for ($i = 1; $i < count($recent); $i++) {
            if ($recent[$i]['usage'] > $recent[$i-1]['usage']) {
                $trend++;
            }
        }

        // If memory is consistently increasing
        return $trend >= 4;
    }

    /**
     * Track data quality issues
     *
     * @param string $type
     * @param int $count
     * @param array $details
     * @return void
     */
    public function trackDataQuality(string $type, int $count = 1, array $details = []): void
    {
        switch ($type) {
            case 'duplicate':
                $this->duplicatesFound += $count;
                break;
            case 'validation_failure':
                $this->validationFailures += $count;
                $this->validationErrors[] = $details;
                break;
            case 'foreign_key_violation':
                $this->foreignKeyViolations += $count;
                break;
            case 'null_value':
                $field = $details['field'] ?? 'unknown';
                $this->nullValueCounts[$field] = ($this->nullValueCounts[$field] ?? 0) + $count;
                break;
        }
    }

    /**
     * Track transformation time
     *
     * @param float $time
     * @return void
     */
    public function trackTransformationTime(float $time): void
    {
        $this->transformationTime += $time;
    }

    /**
     * Track relationship creation
     *
     * @param int $count
     * @return void
     */
    public function trackRelationships(int $count): void
    {
        $this->relationshipsCreated += $count;
    }

    /**
     * Track retry attempt
     *
     * @param string $reason
     * @return void
     */
    public function trackRetry(string $reason = ''): void
    {
        $this->retryAttempts++;
    }

    /**
     * Track connection timeout
     *
     * @return void
     */
    public function trackConnectionTimeout(): void
    {
        $this->connectionTimeouts++;
    }

    /**
     * Track file processing
     *
     * @param string $filename
     * @param int $size
     * @param float $processingTime
     * @return void
     */
    public function trackFileProcessing(string $filename, int $size, float $processingTime): void
    {
        $this->fileProcessing[] = [
            'filename' => $filename,
            'size' => $size,
            'processing_time' => $processingTime,
            'records_per_mb' => $this->recordsAdded / ($size / 1048576),
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Track API call
     *
     * @param string $endpoint
     * @param float $responseTime
     * @param bool $success
     * @return void
     */
    public function trackApiCall(string $endpoint, float $responseTime, bool $success = true): void
    {
        $this->apiCalls[] = [
            'endpoint' => $endpoint,
            'response_time' => $responseTime,
            'success' => $success,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Track cache usage
     *
     * @param bool $hit
     * @return void
     */
    public function trackCache(bool $hit): void
    {
        if ($hit) {
            $this->cacheMetrics['hits']++;
        } else {
            $this->cacheMetrics['misses']++;
        }
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
     * Get average query time
     *
     * @return float
     */
    public function getAverageQueryTime(): float
    {
        if (empty($this->queryTimes)) {
            return 0;
        }

        return round(array_sum($this->queryTimes) / count($this->queryTimes), 4);
    }

    /**
     * Get throughput variance
     *
     * @return float
     */
    public function getThroughputVariance(): float
    {
        if (count($this->throughputMeasurements) < 2) {
            return 0;
        }

        $mean = array_sum($this->throughputMeasurements) / count($this->throughputMeasurements);
        $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $this->throughputMeasurements)) / count($this->throughputMeasurements);

        return round(sqrt($variance), 2);
    }

    /**
     * Get optimal batch size based on efficiency data
     *
     * @return int|null
     */
    public function getOptimalBatchSize(): ?int
    {
        if (empty($this->chunkEfficiency)) {
            return null;
        }

        $bestEfficiency = 0;
        $optimalSize = null;

        foreach ($this->chunkEfficiency as $chunk) {
            if ($chunk['records_per_second'] > $bestEfficiency) {
                $bestEfficiency = $chunk['records_per_second'];
                $optimalSize = $chunk['size'];
            }
        }

        return $optimalSize;
    }

    /**
     * Get cache hit ratio
     *
     * @return float
     */
    public function getCacheHitRatio(): float
    {
        $total = $this->cacheMetrics['hits'] + $this->cacheMetrics['misses'];
        if ($total === 0) {
            return 0;
        }

        return round(($this->cacheMetrics['hits'] / $total) * 100, 2);
    }

    /**
     * Get data quality score
     *
     * @return float
     */
    public function getDataQualityScore(): float
    {
        if ($this->recordsAdded === 0) {
            return 100;
        }

        $totalIssues = $this->duplicatesFound + $this->validationFailures + $this->foreignKeyViolations;
        $qualityScore = (($this->recordsAdded - $totalIssues) / $this->recordsAdded) * 100;

        return round(max(0, $qualityScore), 2);
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
        return $this->formatBytes($bytes);
    }

    /**
     * Get peak memory usage in a human-readable format
     *
     * @return string
     */
    public function getFormattedPeakMemoryUsage(): string
    {
        return $this->formatBytes($this->peakMemoryUsage);
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1073741824, 2) . ' GB';
        }
    }

    /**
     * Get current memory usage in a human-readable format
     *
     * @return string
     */
    public function getCurrentMemoryUsage(): string
    {
        return $this->formatBytes(memory_get_usage());
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
            'efficient' => 1,
            'acceptable' => 5,
            'concerning' => 20,
            'excessive' => 50,
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

    /**
     * Get comprehensive performance summary
     *
     * @return array
     */
    public function getPerformanceSummary(): array
    {
        return [
            'basic_metrics' => [
                'records_added' => $this->recordsAdded,
                'execution_time' => $this->executionTime,
                'records_per_second' => $this->recordsPerSecond,
                'time_per_record' => $this->getTimePerRecord(),
                'memory_usage' => $this->getFormattedMemoryUsage(),
                'peak_memory' => $this->getFormattedPeakMemoryUsage(),
                'memory_per_record' => $this->getMemoryPerRecord(),
                'memory_rating' => $this->getMemoryRating()
            ],
            'database_metrics' => [
                'query_count' => $this->queryCount,
                'average_query_time' => $this->getAverageQueryTime(),
                'transaction_count' => $this->transactionCount,
                'connection_timeouts' => $this->connectionTimeouts
            ],
            'data_quality' => [
                'duplicates_found' => $this->duplicatesFound,
                'validation_failures' => $this->validationFailures,
                'foreign_key_violations' => $this->foreignKeyViolations,
                'data_quality_score' => $this->getDataQualityScore(),
                'transformation_time' => $this->transformationTime
            ],
            'performance_analysis' => [
                'performance_trend' => $this->getPerformanceTrend(),
                'throughput_variance' => $this->getThroughputVariance(),
                'optimal_batch_size' => $this->getOptimalBatchSize(),
                'average_batch_size' => $this->getAverageBatchSize(),
                'retry_attempts' => $this->retryAttempts
            ],
            'business_metrics' => [
                'relationships_created' => $this->relationshipsCreated,
                'cache_hit_ratio' => $this->getCacheHitRatio(),
                'api_calls_count' => count($this->apiCalls),
                'files_processed' => count($this->fileProcessing)
            ]
        ];
    }

    // === SYSTEM RESOURCE HELPER METHODS ===

    /**
     * Get current CPU usage (simplified implementation)
     *
     * @return float
     */
    protected function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2);
        }
        return 0;
    }

    /**
     * Get active database connections
     *
     * @return int
     */
    protected function getActiveConnections(): int
    {
        try {
            $result = DB::select('SHOW STATUS WHERE Variable_name = "Threads_connected"');
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get maximum database connections
     *
     * @return int
     */
    protected function getMaxConnections(): int
    {
        try {
            $result = DB::select('SHOW VARIABLES WHERE Variable_name = "max_connections"');
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Export metrics to array for logging/reporting
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'seeder_class' => $this->seederClass,
            'table_name' => $this->tableName,
            'summary' => $this->getPerformanceSummary(),
            'detailed_metrics' => [
                'batch_data' => $this->batchSizes,
                'memory_timeline' => $this->memoryOverTime,
                'query_times' => $this->queryTimes,
                'validation_errors' => $this->validationErrors,
                'api_calls' => $this->apiCalls,
                'file_processing' => $this->fileProcessing,
                'chunk_efficiency' => $this->chunkEfficiency
            ]
        ];
    }
}
