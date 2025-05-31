<?php
namespace Wimd\Metrics;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Class MemoryWarnings
 *
 * Handles memory warnings based on configured thresholds
 */
class MemoryWarnings
{
    /**
     * Check memory usage against thresholds and trigger warnings if needed
     *
     * @param SeederMetrics $metrics Current seeder metrics
     * @return array|null Warning info or null if no warning
     */
    public static function checkMemoryUsage(SeederMetrics $metrics): ?array
    {
        if (!Config::get('wind.memory.warnings_enabled', false)) {
            return null;
        }

        $currentUsage = memory_get_usage();
        $peak = memory_get_peak_usage();

        $thresholds = [
            'notice' => self::parseMemorySize(Config::get('wind.memory.thresholds.notice', '50M')),
            'warning' => self::parseMemorySize(Config::get('wind.memory.thresholds.warning', '100M')),
            'critical' => self::parseMemorySize(Config::get('wind.memory.thresholds.critical', '200M')),
        ];

        $level = null;
        $message = null;

        // Check against thresholds
        if ($currentUsage > $thresholds['critical']) {
            $level = 'critical';
            $message = "CRITICAL: Memory usage ({$metrics->getFormattedMemoryUsage()}) exceeds critical threshold";
        } elseif ($currentUsage > $thresholds['warning']) {
            $level = 'warning';
            $message = "WARNING: Memory usage ({$metrics->getFormattedMemoryUsage()}) exceeds warning threshold";
        } elseif ($currentUsage > $thresholds['notice']) {
            $level = 'notice';
            $message = "NOTICE: Memory usage ({$metrics->getFormattedMemoryUsage()}) exceeds notice threshold";
        }

        // Check per-record memory if we have added records
        if ($metrics->recordsAdded > 0) {
            $memoryPerRecord = $metrics->getMemoryPerRecord();
            $memoryPerRecordKB = $memoryPerRecord / 1024;

            $perRecordThresholds = [
                'efficient' => Config::get('wind.memory.per_record.efficient', 1),
                'acceptable' => Config::get('wind.memory.per_record.acceptable', 5),
                'concerning' => Config::get('wind.memory.per_record.concerning', 20),
                'excessive' => Config::get('wind.memory.per_record.excessive', 50),
            ];

            $perRecordRating = 'critical';
            if ($memoryPerRecordKB <= $perRecordThresholds['efficient']) {
                $perRecordRating = 'efficient';
            } elseif ($memoryPerRecordKB <= $perRecordThresholds['acceptable']) {
                $perRecordRating = 'acceptable';
            } elseif ($memoryPerRecordKB <= $perRecordThresholds['concerning']) {
                $perRecordRating = 'concerning';
            } elseif ($memoryPerRecordKB <= $perRecordThresholds['excessive']) {
                $perRecordRating = 'excessive';
            }

            // Only upgrade the warning level for concerning or worse per-record usage
            if (in_array($perRecordRating, ['concerning', 'excessive', 'critical']) &&
                ($level === null || $level === 'notice')) {
                $level = $perRecordRating === 'critical' ? 'critical' : 'warning';
                $message = "{$level}: High memory per record (" . round($memoryPerRecordKB, 2) . " KB/record)";
            }
        }

        // Check abort threshold
        $abortThreshold = self::parseMemorySize(Config::get('wind.memory.options.abort_threshold'));
        $shouldAbort = false;

        if ($abortThreshold !== null && $currentUsage > $abortThreshold) {
            $level = 'abort';
            $message = "ABORTING: Memory usage ({$metrics->getFormattedMemoryUsage()}) exceeds abort threshold";
            $shouldAbort = true;
        }

        // Log if needed
        if ($level !== null && Config::get('wind.memory.options.log_excessive_usage', false)) {
            $logLevel = match($level) {
                'notice' => 'info',
                'warning' => 'warning',
                'critical', 'abort' => 'error',
                default => 'debug'
            };

            Log::$logLevel("[WIMD] {$metrics->getShortName()}: {$message}");
        }

        // Return warning info if any
        if ($level !== null) {
            return [
                'level' => $level,
                'message' => $message,
                'usage' => $currentUsage,
                'peak' => $peak,
                'formatted' => $metrics->getFormattedMemoryUsage(),
                'per_record_kb' => $metrics->recordsAdded > 0 ? round($metrics->getMemoryPerRecord() / 1024, 2) : null,
                'abort' => $shouldAbort,
                'optimization_tips' => self::getOptimizationTips($metrics)
            ];
        }

        return null;
    }

    /**
     * Parse memory size from human-readable format to bytes
     *
     * @param string|null $size Size string (e.g., '50M', '1G')
     * @return int|null Size in bytes or null if input is null
     */
    public static function parseMemorySize($size): ?int
    {
        if ($size === null) {
            return null;
        }

        if (is_numeric($size)) {
            return (int)$size;
        }

        $unit = strtoupper(substr($size, -1));
        $value = (int)substr($size, 0, -1);

        return match($unit) {
            'K' => $value * 1024,
            'M' => $value * 1024 * 1024,
            'G' => $value * 1024 * 1024 * 1024,
            default => (int)$size,
        };
    }

    /**
     * Get optimization tips based on metrics
     *
     * @param SeederMetrics $metrics Current seeder metrics
     * @return array|null Array of tips or null if tips disabled
     */
    public static function getOptimizationTips(SeederMetrics $metrics): ?array
    {
        if (!Config::get('wind.memory.options.show_optimization_tips', true)) {
            return null;
        }

        $tips = [];
        $memoryPerRecordKB = $metrics->recordsAdded > 0 ? $metrics->getMemoryPerRecord() / 1024 : 0;

        if ($memoryPerRecordKB > 20) {
            $tips[] = "Consider using chunk() or cursor() to process records in batches";
            $tips[] = "Check for memory leaks in your seeder (unset large variables when no longer needed)";
        }

        if ($metrics->recordsAdded > 1000 && $memoryPerRecordKB > 5) {
            $tips[] = "For large datasets, implement a custom iterator to reduce memory overhead";
        }

        $avgBatchSize = $metrics->getAverageBatchSize();
        if ($avgBatchSize !== null && $avgBatchSize > 500) {
            $tips[] = "Your average batch size ($avgBatchSize) may be too large. Try smaller batches (50-200)";
        }

        return $tips;
    }
}
