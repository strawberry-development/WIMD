<?php

namespace Wimd;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Config\RenderingConfig;
use Wimd\Metrics\MetricsCollector;
use Wimd\Model\DataMetric;
use Wimd\Renderers\ConsoleRenderer;
use Wimd\Renderers\RendererInterface;
use Wimd\Support\ConsoleFormatter;

/**
 * WimdManager
 *
 * This class serves as the core service behind the Wimd facade,
 * managing the registration and execution of data seeders, tracking
 * execution metrics, handling output display, and supporting various
 * operational modes. It acts as an internal API for coordinating and
 * reporting on seeding tasks within the application.
 */
class WimdManager
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * The metrics collector instance.
     *
     * @var MetricsCollector|null
     */
    protected $metrics = null;

    /**
     * Console output interface.
     *
     * @var OutputInterface|null
     */
    protected $output = null;

    /**
     * Start time of the seeding process.
     *
     * @var float
     */
    protected $startTime;

    /**
     * Array of registered seeders and their metrics.
     *
     * @var array
     */
    protected $seeders = [];

    /**
     * Array of unregistered seeders
     *
     * @var array
     */
    protected $unregisteredSeeders = [];

    /**
     * Current operation mode
     *
     * @var string
     */
    private string $mode;

    /**
     * The output renderer.
     *
     * @var RendererInterface|null
     */
    protected ?RendererInterface $renderer = null;

    /**
     * Rendering configuration
     *
     * @var RenderingConfig|null
     */
    private $config = null;

    /**
     * Whether the manager operates in silent mode.
     *
     * @var bool
     */
    protected bool $silent = false;

    /**
     * Cache for seeder table names to avoid repeated computation
     *
     * @var array
     */
    private array $tableNameCache = [];

    /**
     * Whether cleanup has been performed
     *
     * @var bool
     */
    private bool $cleanedUp = false;

    /**
     * Formatter instance (unique)
     *
     * @var ConsoleFormatter|null
     */
    private ?ConsoleFormatter $consoleFormatter = null;

    /**
     * Create a new WIMD manager instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->startTime = microtime(true);

        // Lazy load config and mode to reduce constructor overhead
        $this->lazyInitialize();
    }

    /**
     * Initialize mode configuration lazily
     */
    private function lazyInitialize(): void
    {
        if ($this->config === null) {
            $this->config = new RenderingConfig();
            $this->mode = $this->config->getMode();
        }
    }

    /**
     * Cleanup resources to prevent memory leaks
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Manually cleanup resources
     */
    public function cleanup(): void
    {
        if ($this->cleanedUp) {
            return;
        }

        $this->seeders = [];
        $this->unregisteredSeeders = [];
        $this->tableNameCache = [];

        // Clear object references
        $this->metrics = null;
        $this->renderer = null;
        $this->config = null;
        $this->consoleFormatter = null;

        $this->cleanedUp = true;
    }

    /**
     * Get the metrics collector instance (lazy loaded)
     *
     * @return MetricsCollector
     */
    protected function getMetrics(): MetricsCollector
    {
        if ($this->metrics === null) {
            $this->metrics = new MetricsCollector();
        }
        return $this->metrics;
    }

    /**
     * Get the renderer instance (lazy loaded)
     *
     * @return RendererInterface
     */
    protected function getRenderer(): RendererInterface
    {
        if ($this->renderer === null) {
            $this->renderer = new ConsoleRenderer();
            if ($this->output) {
                $this->renderer->setOutput($this->output);
            }
        }
        return $this->renderer;
    }

    protected function getConsoleFormatter(): ConsoleFormatter
    {
        if ($this->consoleFormatter === null) {
            $this->consoleFormatter = new ConsoleFormatter();
        }
        return $this->consoleFormatter;
    }

    /**
     * Get the configuration instance (lazy loaded)
     *
     * @return RenderingConfig
     */
    protected function getConfig(): RenderingConfig
    {
        return $this->config;
    }

    /**
     * Set the output interface.
     *
     * @param OutputInterface $output
     * @return self
     */
    public function setOutput(OutputInterface $output): self
    {
        $this->output = $output;
        $this->output->setDecorated(true);

        // Update renderer if it exists
        if ($this->renderer) {
            $this->renderer->setOutput($this->output);
        }

        return $this;
    }

    /**
     * Set the mode for WIMD monitoring.
     *
     * @param string $mode 'light' or 'full'
     * @return self
     */
    public function setMode(string $mode): self
    {
        $this->mode = $mode;

        // Reset renderer to apply new mode
        $this->renderer = null;

        return $this;
    }

    /**
     * Register a seeder with the manager.
     *
     * @param string $seederClass
     * @param array $options
     * @return self
     */
    public function registerSeeder(string $seederClass, array $options = []): self
    {
        $tableName = $this->getTableNameFromSeeder($seederClass);

        $this->seeders[$seederClass] = [
            'table' => $tableName,
            'options' => $options,
            'metrics' => $this->getMetrics()->createSeederMetrics($seederClass, $tableName),
        ];

        // Clear unregistered seeders cache to force refresh
        $this->unregisteredSeeders = [];

        return $this;
    }

    /**
     * Update metrics for a seeder.
     *
     * @param string $seederClass
     * @param int $recordsAdded
     * @param float $executionTime
     * @return self
     */
    public function updateMetrics(string $seederClass, int $recordsAdded, float $executionTime): self
    {
        if (isset($this->seeders[$seederClass])) {
            $this->getMetrics()->updateSeederMetrics(
                $this->seeders[$seederClass]['metrics'],
                $recordsAdded,
                $executionTime
            );
        }
        return $this;
    }

    /**
     * Find seeder classes that aren't registered in the $seeders array by scanning the seeder directory.
     *
     * @param string|null $seedersPath Path to the directory containing seeder classes
     * @return array List of unregistered seeder classes with their table names
     */
    public function findUnregisteredSeeders(string $seedersPath = null): array
    {
        // Return cached result if available
        if (!empty($this->unregisteredSeeders)) {
            return $this->unregisteredSeeders;
        }

        // Default seeder path if none provided
        if ($seedersPath === null) {
            $seedersPath = database_path('seeders');
        }

        // Check if directory exists
        if (!is_dir($seedersPath)) {
            return [];
        }

        $unregisteredSeeders = [];

        try {
            // Use DirectoryIterator for better memory efficiency with large directories
            $iterator = new \DirectoryIterator($seedersPath);

            foreach ($iterator as $fileInfo) {
                // Skip non-PHP files and directories
                if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                    continue;
                }

                $className = $fileInfo->getBasename('.php');
                $seederClass = 'Database\\Seeders\\' . $className;

                // Skip DatabaseSeeder or already registered seeders
                if ($className === 'DatabaseSeeder' || isset($this->seeders[$seederClass])) {
                    continue;
                }

                // Only process if class exists to avoid memory overhead
                if (class_exists($seederClass)) {
                    $tableName = $this->getTableNameFromSeeder($seederClass);
                    $unregisteredSeeders[$seederClass] = [
                        'table' => $tableName,
                        'options' => [],
                        'metrics' => $this->getMetrics()->createSeederMetrics($seederClass, $tableName),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log error or handle silently depending on your error handling strategy
            error_log("Error scanning seeders directory: " . $e->getMessage());
            return [];
        }

        // Cache the result
        $this->unregisteredSeeders = $unregisteredSeeders;

        return $unregisteredSeeders;
    }

    /**
     * Display the final seeding report.
     *
     * @return string|null The report as a string (if using BufferedOutput)
     */
    public function displayReport(): ?string
    {
        $totalTime = microtime(true) - $this->startTime;
        $data = new DataMetric($this->getAllSeeders(), $totalTime);
        return $this->getRenderer()->renderReport($data);
    }

    /**
     * Get all seeders (registered and unregistered) efficiently
     *
     * @return array
     */
    private function getAllSeeders(): array
    {
        // Lazy load unregistered seeders only when needed
        $unregistered = empty($this->unregisteredSeeders)
            ? $this->findUnregisteredSeeders()
            : $this->unregisteredSeeders;

        return array_merge($this->seeders, $unregistered);
    }

    /**
     * Get table name from seeder class name with caching.
     *
     * @param string $seederName
     * @return string
     */
    protected function getTableNameFromSeeder(string $seederName): string
    {
        // Check cache first
        if (isset($this->tableNameCache[$seederName])) {
            return $this->tableNameCache[$seederName];
        }

        // Extract the class name without namespace
        $classNameParts = explode('\\', $seederName);
        $className = end($classNameParts);

        // Convert CamelCase to snake_case and remove "Seeder" suffix
        $tableName = preg_replace('/Seeder$/', '', $className);
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tableName));

        // Cache the result
        $this->tableNameCache[$seederName] = $tableName;

        return $tableName;
    }

    /**
     * Get the current WIMD mode.
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Get the current config.
     *
     * @return RenderingConfig
     */
    public function getConfigInstance(): RenderingConfig
    {
        return $this->getConfig();
    }

    public function getFormatterInstance(): ConsoleFormatter
    {
        return $this->getConsoleFormatter();
    }

    /**
     * Get seeder metrics by class name.
     *
     * @param string $seederClass
     * @return array|null
     */
    public function getSeederMetrics(string $seederClass): ?array
    {
        return $this->seeders[$seederClass]['metrics'] ?? null;
    }

    /**
     * Get the silent mode status.
     *
     * @return bool
     */
    public function isSilent(): bool
    {
        return $this->silent;
    }

    /**
     * Set the silent mode status.
     *
     * @param bool $silent
     * @return self
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;
        return $this;
    }

    /**
     * Get current memory usage information.
     *
     * @return array
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'current_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'seeders_count' => count($this->seeders),
            'unregistered_count' => count($this->unregisteredSeeders),
        ];
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Clear all cached data to free memory.
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->unregisteredSeeders = [];
        $this->tableNameCache = [];
        return $this;
    }

    /**
     * Get statistics about the manager's current state.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'registered_seeders' => count($this->seeders),
            'unregistered_seeders' => count($this->unregisteredSeeders),
            'cached_table_names' => count($this->tableNameCache),
            'execution_time' => microtime(true) - $this->startTime,
            'memory_usage' => $this->getMemoryUsage(),
            'mode' => $this->getMode(),
            'silent' => $this->silent,
            'cleaned_up' => $this->cleanedUp,
        ];
    }

    /**
     * Simple function to write to log file, creates path and file if needed
     */
    function writeLog($filePath, $message)
    {
        // Create file if it doesn't exist
        if (!file_exists($filePath)) {
            touch($filePath);
            chmod($filePath, 0644);
        }

        // Write log entry with timestamp
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;

        file_put_contents($filePath, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
