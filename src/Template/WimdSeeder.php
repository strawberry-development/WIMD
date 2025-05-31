<?php

namespace Wimd\Template;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psy\Exception\ThrowUpException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Wimd\Console\Helper\WindProgressBar;
use Wimd\Contracts\WimdSeederInterface;
use Wimd\Support\ConsoleFormatter;

/**
 * Abstract base class for all WIMD seeders with advanced progress tracking and batch processing.
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

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->output = new ConsoleOutput();
        $this->output->setDecorated(true);

        // Load configuration with defaults
        $this->loadConfig();

        // Register this seeder with WIMD
        $options = [
            'lightItems' => $this->lightItems,
            'fullItems' => $this->fullItems,
            'mode' => $this->mode,
        ];

        $this->consoleFormatter = new ConsoleFormatter();

        $this->setMode(app('wimd')->getMode());
        app('wimd')->registerSeeder(static::class, $options);
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

        $this->bar = config(
            'wimd.styling.progress_format.bar',
            '[%bar%] %percent:3s%%'
        );

        $this->formatBase = config(
            'wimd.styling.progress_format.base',
            '⏱️ %elapsed:6s% spend / %estimated:-6s% left'
        );

        $this->formatCompletion = config(
            'wimd.styling.progress_format.full',
            ' | 🧠 %memory:6s%'
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
     * Main seeder run method - wraps the actual seeding with progress tracking
     * @throws ThrowUpException
     */
    public function run(): void
    {
        // Suppress default Laravel output
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
            // Consider as light mode, check if it can be designate
            if ($this->lightItems !== null) {
                $this->totalItems = $this->lightItems;
            }
        }

        $text = $this->consoleFormatter->formatLine(
            "{$seedName} Seeder gray{(Mode: {$this->mode} | Target: {$this->totalItems})}",
            "+yellow{RUNNING}"
        );
        $this->output->writeln("  " . $text);


        // Call the prepare method to calculate totals
        try {
            $this->prepare();
        } catch (Throwable $e) {
            $this->handleError("Error in preparation phase", $e);
            throw new ThrowUpException("Failed to prepare {$seedName} seeder");
        }

        // Enforce min/max if set
        if ($this->lightItems !== null && $this->totalItems < $this->lightItems) {
            $this->totalItems = $this->lightItems;
        }

        if ($this->fullItems !== null && $this->totalItems > $this->fullItems) {
            $this->totalItems = $this->fullItems;
        }

        // Start the progress bar if total items were set
        if ($this->totalItems > 0) {
            $this->startProgress($this->totalItems, null);
        }

        try {
            // Execute the actual seeding logic
            $this->seed();

            // Insert any remaining items in batch collectors
            $this->flushAllBatchCollectors();

            // Finish the progress bar
            if ($this->progressBar) {
                // Make sure progress bar is at 100% at the end
                if ($this->itemsProcessed < $this->totalItems) {
                    $this->advanceProgress($this->totalItems - $this->itemsProcessed);
                }
                $this->finishProgress(null);
            }

            // Create summary information
            $itemsProcessedSummary = number_format($this->itemsProcessed);
            $executionTime = microtime(true) - $this->seederStartTime;
            $itemsPerSecond = ($executionTime > 0) ? number_format($this->itemsProcessed / $executionTime, 1) : 0;

            $summary = "(Items: {$itemsProcessedSummary} | Per second: {$itemsPerSecond}/s";

            // Add error information if any errors occurred
            if ($this->errorCount > 0) {
                $summary .= " | Errors: {$this->errorCount}";
            }
            $summary .= ")";

            $text = $this->consoleFormatter->formatLine(
                "{$seedName} Seeder gray{{$summary}}",
                "+green{DONE}",
                ["newline" => true]
            );
            $this->output->writeln('  ' . $text);

            // Show error summary if any occurred
            if ($this->errorCount > 0) {
                $this->output->writeln("  <fg=yellow;options=bold>⚠️  Completed with {$this->errorCount} error(s)</>");
            }

            // Update metrics
            $executionTime = microtime(true) - $this->seederStartTime;
            app('wimd')->updateMetrics(static::class, $this->itemsProcessed, $executionTime, $this->errorCount);

        } catch (Throwable $e) {
            if ($this->progressBar) {
                $this->progressBar->clear();
            }
            $this->handleError("Fatal error in {$seedName} seeder", $e, true);
            throw new ThrowUpException("  Failed to seed {$seedName}", 0, $e);
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

        // Clear progress bar if it exists
        if ($this->progressBar) {
            $this->progressBar->clear();
        }

        // Output error details
        $this->output->writeln([
            "",
            "  <fg=red;options=bold>❌ {$errorType}: {$message}</>",
            "  <fg=red>{$errorMessage}</>",
            "  <fg=gray>Location: {$errorLocation}</>"
        ]);

        // Log the full error with stack trace
        Log::error("{$message}: {$errorMessage}", [
            'exception' => $e,
            'seeder' => static::class
        ]);

        $this->errorCount++;

        // Check if we should abort due to too many errors
        if (!$isFatal && $this->maxErrors > 0 && $this->errorCount >= $this->maxErrors && !$this->continueOnError) {
            throw new ThrowUpException(
                "Maximum error threshold ({$this->maxErrors}) reached. Aborting seeder.",
            );
        }

        // Restart progress bar if needed
        if ($this->progressBar && !$isFatal) {
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
        if ($message) {
            $this->output->writeln($message);
        }
        $this->progressBar = new WindProgressBar($this->output, $total);
        $this->progressBar->setRedrawFrequency(max(1, min(100, intval($total / 100))));
        $this->progressBar->setBarWidth(50);
        $this->progressBar->setBarCharacter('#');
        $this->progressBar->setEmptyBarCharacter('.');
        $this->progressBar->setProgressCharacter('');

        // Add memory usage if in full mode
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
        if ($this->progressBar) {
            $this->progressBar->advance($step);

            // Force recalculation of time estimates
            if ($this->itemsProcessed % 100 === 0) {
                $this->progressBar->display();
            }
        }
        $this->itemsProcessed += $step;
    }

    /**
     * Finish the progress bar
     *
     * @param string|null $message Message to display after the progress bar
     */
    protected function finishProgress(?string $message = 'Seeding completed!'): void
    {
        if ($this->progressBar) {
            $this->progressBar->finish();
            $this->output->writeln('');
            if ($message) {
                $this->output->writeln($message);
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
        $batches = array_chunk($data, $batchSize);

        try {
            foreach ($batches as $batch) {
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
            }

            // Clear the data array to free memory
            $data = [];
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

        // Initialize batch collector for this table if it doesn't exist
        if (!isset($this->batchCollectors[$table])) {
            $this->batchCollectors[$table] = [
                'items' => [],
                'batchSize' => $batchSize
            ];
        }

        // Add item to the collector
        $this->batchCollectors[$table]['items'][] = $item;

        // Check if we reached the batch size
        if (count($this->batchCollectors[$table]['items']) >= $batchSize) {
            $this->flushBatchCollector($table);
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
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function createWithFactory(string $model, int $count = 1, ?int $batchSize = null, ?callable $customizer = null)
    {
        $batchSize = $batchSize ?? $this->batchSize;
        $collection = collect();

        // Calculate number of full batches and remainder
        $fullBatches = floor($count / $batchSize);
        $remainder = $count % $batchSize;

        // Process full batches
        for ($i = 0; $i < $fullBatches; $i++) {
            try {
                if ($this->useTransactions) {
                    DB::beginTransaction();
                }

                $factory = $model::factory()->count($batchSize);

                if ($customizer) {
                    $result = $factory->state($customizer)->create();
                } else {
                    $result = $factory->create();
                }

                if ($this->useTransactions) {
                    DB::commit();
                }

                $collection = $collection->merge($result);
                $this->advanceProgress($batchSize);
            } catch (Throwable $e) {
                if ($this->useTransactions) {
                    DB::rollBack();
                }

                $this->handleError("Error creating models with factory for '{$model}'", $e);

                if (!$this->continueOnError) {
                    throw $e;
                }
            }
        }

        // Process remainder if any
        if ($remainder > 0) {
            try {
                if ($this->useTransactions) {
                    DB::beginTransaction();
                }

                $factory = $model::factory()->count($remainder);

                if ($customizer) {
                    $result = $factory->state($customizer)->create();
                } else {
                    $result = $factory->create();
                }

                if ($this->useTransactions) {
                    DB::commit();
                }

                $collection = $collection->merge($result);
                $this->advanceProgress($remainder);
            } catch (Throwable $e) {
                if ($this->useTransactions) {
                    DB::rollBack();
                }

                $this->handleError("Error creating models with factory for '{$model}'", $e);

                if (!$this->continueOnError) {
                    throw $e;
                }
            }
        }

        return $collection;
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
        // Return from cache if available
        if (isset($this->dataCache[$cacheKey])) {
            return $this->dataCache[$cacheKey];
        }

        // Query the database
        try {
            $query = DB::table($table);

            foreach ($where as $field => $value) {
                $query->where($field, $value);
            }

            $result = $query->value($select);

            // Store in cache
            $this->dataCache[$cacheKey] = $result;

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
            throw new \InvalidArgumentException("Invalid mode: {$mode}. Must be 'light' or 'full'");
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
}
