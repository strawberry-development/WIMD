<?php

namespace Wimd\Template;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psy\Exception\ThrowUpException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wimd\Console\Helper\WindProgressBar;
use Wimd\Contracts\WimdSeederInterface;
use Wimd\Facades\Wimd;
use Wimd\Console\Helper\ConsoleFormatter;

/**
 * WimdSeeder
 *
 * Abstract base class for all WIMD seeders, providing a standardized structure
 * for implementing seeders with advanced progress tracking, batch processing,
 * and performance metrics. This class extends Laravel's Seeder and integrates
 * with the Wimd system to support consistent data seeding operations across
 * different modules.
 */
abstract class WimdSeeder extends Seeder implements WimdSeederInterface
{
    /**
     * Batch size for insertions
     */
    protected int $batchSize;

    /**
     * Progress bar instance
     */
    protected ?WindProgressBar $progressBar = null;

    /**
     * Console output instance
     */
    protected OutputInterface $output;

    /**
     * Items processed count
     */
    protected int $itemsProcessed = 0;

    /**
     * Total items to process
     */
    protected int $totalItems = 0;

    /**
     * Seeder start time
     */
    protected float $seederStartTime;

    /**
     * Minimum items to seed (optional)
     */
    protected ?int $lightItems = null;

    /**
     * Maximum items to seed (optional)
     */
    protected ?int $fullItems = null;

    /**
     * The seeding mode (light or full)
     */
    protected string $mode;

    /**
     * Whether to use transactions for each batch
     */
    protected bool $useTransactions = true;

    /**
     * Whether to continue on errors
     */
    protected bool $continueOnError = false;

    /**
     * Batch collectors for automatically managing batch inserts
     */
    protected array $batchCollectors = [];

    /**
     * Progress bar base format
     */
    protected string $formatBase;
    protected string $bar;

    /**
     * Progress bar completion format addition
     */
    protected string $formatCompletion;

    /**
     * Store error counts during execution
     */
    protected int $errorCount = 0;

    /**
     * Maximum errors before aborting (0 to never abort)
     */
    protected int $maxErrors = 5;

    /**
     * Cache common data to reduce DB hits
     */
    protected array $dataCache = [];

    protected ConsoleFormatter $consoleFormatter;

    protected bool $silent;

    /**
     * Memory management properties
     */
    protected int $maxCacheSize = 1000;
    protected int $memoryCheckInterval = 100;
    protected int $lastMemoryCheck = 0;
    protected string $memoryThreshold = '50M';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->output = new ConsoleOutput();

        $this->loadConfig();

        $options = [
            'lightItems' => $this->lightItems,
            'fullItems' => $this->fullItems,
            'mode' => $this->mode,
        ];

        $this->consoleFormatter = new ConsoleFormatter();

        $this->setMode(Wimd::getMode());
        Wimd::registerSeeder(static::class, $options);
        $this->silent = Wimd::isSilent();
    }

    /**
     * Load configuration from config files with sensible defaults
     */
    protected function loadConfig(): void
    {
        $this->mode = config('wimd.mode', 'full');
        $this->batchSize = config('wimd.batch_size', 500);
        $this->useTransactions = config('wimd.use_transactions', true);
        $this->continueOnError = config('wimd.continue_on_error', false);
        $this->maxErrors = config('wimd.max_errors', 5);
        $this->maxCacheSize = config('wimd.max_cache_size', 1000);
        $this->memoryCheckInterval = config('wimd.memory_check_interval', 100);
        $this->memoryThreshold = config('wimd.memory.thresholds.warning', '50M');

        $this->bar = config(
            'wimd.styling.progress_format.bar',
            '[%bar%] %percent:3s%%'
        );

        $this->formatBase = config(
            'wimd.styling.progress_format.base',
            '%elapsed:6s% spend / %remaining:-6s% left'
        );

        $this->formatCompletion = " " . config(
                'wimd.styling.progress_format.full',
                '| Memory %memory:6s%s'
            );
    }

    /**
     * Set the output interface.
     */
    public function setOutput(OutputInterface $output): self
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Write output only if not in silent mode
     */
    protected function writeOutput(string|array $messages, bool $newline = false, int $options = 0): void
    {
        if ($this->silent) {
            return;
        }

        if ($newline) {
            $this->output->writeln($messages, $options);
        } else {
            $this->output->write($messages, false, $options);
        }
    }

    /**
     * Check memory usage and perform cleanup if needed
     */
    protected function checkMemoryUsage(): void
    {
        $this->lastMemoryCheck++;

        if ($this->lastMemoryCheck % $this->memoryCheckInterval !== 0) {
            return;
        }

        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryString($this->memoryThreshold);

        if ($memoryUsage > $memoryLimit) {
            $this->performMemoryCleanup();

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Perform memory cleanup operations
     */
    protected function performMemoryCleanup(): void
    {
        if (count($this->dataCache) > $this->maxCacheSize / 2) {
            $keysToRemove = array_slice(array_keys($this->dataCache), 0, (int)($this->maxCacheSize * 0.75));
            foreach ($keysToRemove as $key) {
                unset($this->dataCache[$key]);
            }
            unset($keysToRemove);
        }

        // Flush all batch collectors
        foreach ($this->batchCollectors as $table => &$collector) {
            if (!empty($collector['items'])) {
                $this->flushBatchCollector($table);
            }
        }
        unset($collector);

        // Clear any large arrays in memory
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
    }

    /**
     * Parse memory string to bytes
     */
    protected function parseMemoryString(string $memory): int
    {
        $unit = strtoupper(substr($memory, -1));
        $value = (int) substr($memory, 0, -1);

        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int) $memory;
        }
    }

    /**
     * Main seeder run method - wraps the actual seeding with progress tracking
     * @throws ThrowUpException
     */
    public function run(): void
    {
        $this->command?->getOutput()?->setVerbosity(OutputInterface::VERBOSITY_QUIET);

        $className = static::class;
        $seedName = substr($className, strrpos($className, '\\') + 1);
        $seedName = str_replace('Seeder', '', $seedName);

        $this->seederStartTime = microtime(true);
        $this->errorCount = 0;

        $this->prepare();

        if ($this->mode == "full") {
            if ($this->fullItems !== null) {
                $this->totalItems = $this->fullItems;
            }
        } else {
            if ($this->lightItems !== null) {
                $this->totalItems = $this->lightItems;
            }
        }

        if (!$this->silent) {
            $text = $this->consoleFormatter->formatLine(
                "{$seedName} Seeder",
                "gray{Mode: {$this->mode}, Target: {$this->totalItems}} +yellow{RUNNING}"
            );
            $this->writeOutput("  " . $text, true);
        }

        try {
            $this->prepare();
        } catch (Throwable $e) {
            $this->handleError("Error in preparation phase", $e);
            throw new ThrowUpException("Failed to prepare {$seedName} seeder");
        }

        if ($this->lightItems !== null && $this->totalItems < $this->lightItems) {
            $this->totalItems = $this->lightItems;
        }

        if ($this->fullItems !== null && $this->totalItems > $this->fullItems) {
            $this->totalItems = $this->fullItems;
        }

        if ($this->totalItems > 0 && !$this->silent) {
            $this->startProgress($this->totalItems, null);
        }

        try {
            $this->seed();

            $this->flushAllBatchCollectors();

            if ($this->progressBar && !$this->silent) {
                if ($this->itemsProcessed < $this->totalItems) {
                    $this->advanceProgress($this->totalItems - $this->itemsProcessed);
                }
                $this->finishProgress(null);
            }

            if (!$this->silent) {
                $itemsProcessedSummary = number_format($this->itemsProcessed);
                $executionTime = microtime(true) - $this->seederStartTime;
                $itemsPerSecond = ($executionTime > 0) ? number_format($this->itemsProcessed / $executionTime, 1) : 0;

                $summary = "Items: {$itemsProcessedSummary}, Per second: {$itemsPerSecond}/s";

                if ($this->errorCount > 0) {
                    $summary .= ", Errors: {$this->errorCount}";
                }
                $summary .= ")";

                $text = $this->consoleFormatter->formatLine(
                    "{$seedName} Seeder",
                    "gray{{$summary}} +green{DONE}",
                    ["newline" => true]
                );
                $this->writeOutput('  ' . $text, true);

                if ($this->errorCount > 0) {
                    $this->writeOutput("  <fg=yellow;options=bold>⚠️  Completed with {$this->errorCount} error(s)</>", true);
                }
            }

            $executionTime = microtime(true) - $this->seederStartTime;
            app('wimd')->updateMetrics(static::class, $this->itemsProcessed, $executionTime, $this->errorCount);

        } catch (Throwable $e) {
            if ($this->progressBar && !$this->silent) {
                $this->progressBar->clear();
            }
            $this->handleError("Fatal error in {$seedName} seeder", $e, true);
            throw new ThrowUpException("  Failed to seed {$seedName}", 0, $e);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Clean up resources
     */
    protected function cleanup(): void
    {
        // Clear all arrays explicitly
        $this->dataCache = [];
        $this->batchCollectors = [];

        // Reset progress bar reference
        $this->progressBar = null;

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Clear memory caches if available
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
    }

    /**
     * Format memory usage
     */
    protected function formatMemoryUsage(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Handle errors in a consistent way
     */
    protected function handleError(string $message, Throwable $e, bool $isFatal = false): void
    {
        $errorType = $isFatal ? 'FATAL ERROR' : 'ERROR';
        $errorMessage = $e->getMessage();
        $errorLocation = basename($e->getFile()) . ':' . $e->getLine();

        if ($this->progressBar && !$this->silent) {
            $this->progressBar->clear();
        }

        if (!$this->silent) {
            $this->writeOutput([
                "",
                "  <fg=red;options=bold>{$errorType}: {$message}</>",
                "  <fg=red>{$errorMessage}</>",
                "  <fg=gray>Location: {$errorLocation}</>"
            ], true);
        }

        Log::error("{$message}: {$errorMessage}", [
            'exception' => $e,
            'seeder' => static::class
        ]);

        $this->errorCount++;

        if (!$isFatal && $this->maxErrors > 0 && $this->errorCount >= $this->maxErrors && !$this->continueOnError) {
            throw new ThrowUpException(
                "Maximum error threshold ({$this->maxErrors}) reached. Aborting seeder.",
            );
        }

        if ($this->progressBar && !$isFatal && !$this->silent) {
            $this->progressBar->display();
        }
    }

    /**
     * Prepare the seeder (calculate totals, etc.)
     * Override this in child classes to set $this->totalItems
     */
    protected function prepare(): void
    {
        // By default, do nothing
        // Override in child classes to calculate $this->totalItems
    }

    /**
     * Actual seeding logic
     * Must be implemented by child classes
     */
    abstract protected function seed(): void;

    /**
     * Start a progress bar with the given total steps
     *
     * @param int $total Total number of steps
     * @param string|null $message Message to display before the progress bar
     */
    protected function startProgress(int $total, ?string $message = 'Seeding data: '): void
    {
        if ($this->silent) {
            return;
        }

        if ($message) {
            $this->writeOutput($message, true);
        }
        $this->progressBar = new WindProgressBar($this->output, $total);
        $this->progressBar->setRedrawFrequency(2500);
        $this->progressBar->setBarWidth(50);
        $this->progressBar->setBarCharacter('#');
        $this->progressBar->setEmptyBarCharacter('.');
        $this->progressBar->setProgressCharacter('');

        if ($this->mode === 'full') {
            $this->formatBase .= $this->formatCompletion;
        }

        $this->progressBar->setFormat($this->bar, $this->formatBase);
        $this->progressBar->start();
    }

    /**
     * Advance the progress bar by the given step
     *
     * @param int $step Number of steps to advance
     */
    protected function advanceProgress(int $step = 1): void
    {
        if ($this->progressBar && !$this->silent) {
            $this->progressBar->advance($step);

            if ($this->itemsProcessed % 100 === 0) {
                $this->progressBar->display();
            }
        }
        $this->itemsProcessed += $step;
        $this->checkMemoryUsage();
    }

    /**
     * Finish the progress bar
     *
     * @param string|null $message Message to display after the progress bar
     */
    protected function finishProgress(?string $message = 'Seeding completed!'): void
    {
        if ($this->progressBar && !$this->silent) {
            $this->progressBar->finish();
            $this->writeOutput('', true);
            if ($message) {
                $this->writeOutput($message, true);
            }
        }
    }

    /**
     * Insert data in batches with progress tracking
     *
     * @param string $table Table name
     * @param array $data Data to insert
     * @param int|null $batchSize Custom batch size (optional)
     */
    protected function batchInsert(string $table, array &$data, ?int $batchSize = null): void
    {
        if (empty($data)) {
            return;
        }

        $batchSize = $batchSize ?? $this->batchSize;
        $totalItems = count($data);

        try {
            for ($i = 0; $i < $totalItems; $i += $batchSize) {
                $batch = array_slice($data, $i, $batchSize);

                if ($this->useTransactions) {
                    DB::beginTransaction();
                }

                try {
                    DB::table($table)->insert($batch);

                    if ($this->useTransactions) {
                        DB::commit();
                    }

                    $this->advanceProgress(count($batch));
                } catch (Throwable $e) {
                    if ($this->useTransactions) {
                        DB::rollBack();
                    }

                    $this->handleError("Error inserting batch in table '{$table}'", $e);

                    if (!$this->continueOnError) {
                        throw $e;
                    }
                }

                unset($batch); // Free batch memory immediately
            }

            $data = []; // Clear original array
        } catch (Throwable $e) {
            if (!$this->continueOnError) {
                throw $e;
            }
        }
    }

    /**
     * Insert a single item with progress tracking
     *
     * @param string $table Table name
     * @param array $item Single item to insert
     * @return bool
     */
    protected function insertItem(string $table, array $item): bool
    {
        try {
            if ($this->useTransactions) {
                DB::beginTransaction();
            }

            $result = DB::table($table)->insert($item);

            if ($this->useTransactions) {
                DB::commit();
            }

            $this->advanceProgress();
            return $result;
        } catch (Throwable $e) {
            if ($this->useTransactions) {
                DB::rollBack();
            }

            $this->handleError("Error inserting item in table '{$table}'", $e);

            if (!$this->continueOnError) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Insert a single item and return its ID with progress tracking
     *
     * @param string $table Table name
     * @param array $item Single item to insert
     * @return int|null
     */
    protected function insertItemAndGetId(string $table, array $item): ?int
    {
        try {
            if ($this->useTransactions) {
                DB::beginTransaction();
            }

            $id = DB::table($table)->insertGetId($item);

            if ($this->useTransactions) {
                DB::commit();
            }

            $this->advanceProgress();
            return $id;
        } catch (Throwable $e) {
            if ($this->useTransactions) {
                DB::rollBack();
            }

            $this->handleError("Error inserting item in table '{$table}'", $e);

            if (!$this->continueOnError) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Update an item with progress tracking
     *
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where Where condition
     * @return int Number of affected rows
     */
    protected function updateItem(string $table, array $data, array $where): int
    {
        try {
            if ($this->useTransactions) {
                DB::beginTransaction();
            }

            $query = DB::table($table);

            foreach ($where as $field => $value) {
                $query->where($field, $value);
            }

            $result = $query->update($data);

            if ($this->useTransactions) {
                DB::commit();
            }

            $this->advanceProgress();
            return $result;
        } catch (Throwable $e) {
            if ($this->useTransactions) {
                DB::rollBack();
            }

            $this->handleError("Error updating item in table '{$table}'", $e);

            if (!$this->continueOnError) {
                throw $e;
            }

            return 0;
        }
    }

    /**
     * Prepare an item for batch insertion
     * Automatically handles batch insertion when batch size is reached
     *
     * @param string $table Table name
     * @param array $item Single item to prepare for insertion
     * @param int|null $batchSize Custom batch size (optional)
     */
    protected function prepareToInsert(string $table, array $item, ?int $batchSize = null): void
    {
        $batchSize = $batchSize ?? $this->batchSize;

        if (!isset($this->batchCollectors[$table])) {
            $this->batchCollectors[$table] = [
                'items' => [],
                'batchSize' => $batchSize
            ];
        }

        $this->batchCollectors[$table]['items'][] = $item;

        if (count($this->batchCollectors[$table]['items']) >= $batchSize) {
            $this->flushBatchCollector($table);
        }

        // Check memory after every 50 items across all collectors
        static $itemCounter = 0;
        if (++$itemCounter % 50 === 0) {
            $this->checkMemoryUsage();
        }
    }

    /**
     * Process and insert any items in the batch collector for a specific table
     *
     * @param string $table Table name
     */
    protected function flushBatchCollector(string $table): void
    {
        if (isset($this->batchCollectors[$table]) && !empty($this->batchCollectors[$table]['items'])) {
            $this->batchInsert($table, $this->batchCollectors[$table]['items']);
            $this->batchCollectors[$table]['items'] = [];
        }
    }

    /**
     * Process and insert any items in all batch collectors
     * Called automatically at the end of the seeder run
     */
    protected function flushAllBatchCollectors(): void
    {
        foreach (array_keys($this->batchCollectors) as $table) {
            $this->flushBatchCollector($table);
        }
    }

    /**
     * Factory helper that wraps factory creation with progress tracking and batch processing
     *
     * @param string $model Model class name
     * @param int $count Number of models to create
     * @param int|null $batchSize Custom batch size (optional)
     * @param callable|null $customizer Optional callback to customize each model's attributes before creation
     * @return WimdSeeder
     * @throws ThrowUpException
     * @throws Throwable
     */
    protected function createWithFactory(string $model, int $count = 1, ?int $batchSize = null, ?callable $customizer = null): WimdSeeder
    {
        $batchSize = $batchSize ?? $this->batchSize;
        $processed = 0;

        while ($processed < $count) {
            $currentBatchSize = min($batchSize, $count - $processed);

            try {
                if ($this->useTransactions) {
                    DB::beginTransaction();
                }

                // Don't store the created models in memory
                $factory = $model::factory()->count($currentBatchSize);

                if ($customizer) {
                    $factory->state($customizer);
                }

                // Create without storing results
                $factory->create();
                unset($factory);

                if ($this->useTransactions) {
                    DB::commit();
                }

                $this->advanceProgress($currentBatchSize);
                $processed += $currentBatchSize;

                // More frequent garbage collection for large datasets
                if ($processed % ($batchSize * 2) === 0 && function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

            } catch (Throwable $e) {
                if ($this->useTransactions) {
                    DB::rollBack();
                }

                $this->handleError("Error creating models with factory for '{$model}'", $e);

                if (!$this->continueOnError) {
                    throw $e;
                }

                $processed += $currentBatchSize; // Skip this batch
            }
        }

        return $this;
    }

    /**
     * Clear data from a table with proper error handling
     *
     * @param string $table Table name
     * @return bool
     */
    protected function clearTable(string $table): bool
    {
        try {
            if ($this->useTransactions) {
                DB::beginTransaction();
            }

            DB::table($table)->delete();

            if ($this->useTransactions) {
                DB::commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($this->useTransactions) {
                DB::rollBack();
            }

            $this->handleError("Error clearing table '{$table}'", $e);

            if (!$this->continueOnError) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Get a cached lookup value or retrieve it from database
     *
     * @param string $cacheKey Unique key for the cache
     * @param string $table Table to query
     * @param array $where Where conditions
     * @param string $select Column to select
     * @return mixed|null
     */
    protected function getCachedLookup(string $cacheKey, string $table, array $where, string $select)
    {
        if (isset($this->dataCache[$cacheKey])) {
            return $this->dataCache[$cacheKey];
        }

        try {
            $query = DB::table($table);

            foreach ($where as $field => $value) {
                $query->where($field, $value);
            }

            $result = $query->value($select);

            if (count($this->dataCache) < $this->maxCacheSize) {
                $this->dataCache[$cacheKey] = $result;
            }

            return $result;
        } catch (Throwable $e) {
            $this->handleError("Error retrieving cached lookup for '{$cacheKey}'", $e);

            if (!$this->continueOnError) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Get the current seeding mode
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Set the seeding mode
     *
     * @param string $mode 'light' or 'full'
     * @return $this
     */
    public function setMode(string $mode): self
    {
        if (!in_array($mode, ['light', 'full'])) {
            throw new InvalidArgumentException("Invalid mode: {$mode}. Must be 'light' or 'full'");
        }

        $this->mode = $mode;
        return $this;
    }

    /**
     * Set minimum number of items to seed
     */
    public function setLightItems(int $min): self
    {
        $this->lightItems = $min;
        return $this;
    }

    /**
     * Set maximum number of items to seed
     */
    public function setFullItems(int $max): self
    {
        $this->fullItems = $max;
        return $this;
    }

    /**
     * Set whether to use transactions
     */
    public function setUseTransactions(bool $use): self
    {
        $this->useTransactions = $use;
        return $this;
    }

    /**
     * Set whether to continue on errors
     */
    public function setContinueOnError(bool $continue): self
    {
        $this->continueOnError = $continue;
        return $this;
    }

    /**
     * Set maximum errors before aborting
     */
    public function setMaxErrors(int $max): self
    {
        $this->maxErrors = $max;
        return $this;
    }

    /**
     * Set batch size for insertions
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * Set silent mode
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;
        return $this;
    }

    /**
     * Check if seeder is in silent mode
     */
    public function isSilent(): bool
    {
        return $this->silent;
    }

    /**
     * Set maximum cache size
     */
    public function setMaxCacheSize(int $size): self
    {
        $this->maxCacheSize = $size;
        return $this;
    }

    /**
     * Set memory check interval
     */
    public function setMemoryCheckInterval(int $interval): self
    {
        $this->memoryCheckInterval = $interval;
        return $this;
    }

    /**
     * Set memory threshold
     */
    public function setMemoryThreshold(string $threshold): self
    {
        $this->memoryThreshold = $threshold;
        return $this;
    }
}
