<?php

namespace Wimd;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Config\RenderingConfig;
use Wimd\Metrics\MetricsCollector;
use Wimd\Renderers\ConsoleRenderer;
use Wimd\Renderers\RendererInterface;

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
     * @var MetricsCollector
     */
    protected $metrics;

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

    private $mode;

    /**
     * The output renderer.
     *
     * @var RendererInterface
     */
    protected $renderer;

    private RenderingConfig $config;

    /**
     * Whether the manager operates in silent mode.
     *
     * @var bool
     */
    protected bool $silent = false;


    /**
     * Create a new WIMD manager instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->metrics = new MetricsCollector();
        $this->startTime = microtime(true);

        $this->config = new RenderingConfig();

        $this->mode = $this->config->getMode();
        $this->setMode($this->mode);
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
        // Ensure renderer is created
        $this->renderer = new ConsoleRenderer();
        $this->mode = $mode;
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
            'metrics' => $this->metrics->createSeederMetrics($seederClass, $tableName),
        ];

        $this->unregisteredSeeders = $this->findUnregisteredSeeders();
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
            $this->metrics->updateSeederMetrics(
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
     * @param string $seedersPath Path to the directory containing seeder classes
     * @return array List of unregistered seeder classes with their table names
     */
    public function findUnregisteredSeeders(string $seedersPath = null): array
    {
        // Default seeder path if none provided
        if ($seedersPath === null) {
            $seedersPath = database_path('seeders');
        }

        $unregisteredSeeders = [];

        // Get all PHP files in the seeders directory
        $files = glob($seedersPath . '/*.php');

        foreach ($files as $file) {
            // Extract class name from filename (without .php extension)
            $className = basename($file, '.php');

            // Add namespace if needed - adjust this to match your application structure
            $seederClass = 'Database\\Seeders\\' . $className;

            // Skip DatabaseSeeder or already registered seeders
            if ($className === 'DatabaseSeeder' || isset($this->seeders[$seederClass])) {
                continue;
            }

            // Make sure the class exists
            if (class_exists($seederClass)) {
                $tableName = $this->getTableNameFromSeeder($seederClass);
                $unregisteredSeeders[$seederClass] = [
                    'table' => $tableName,
                    'options' => [],
                    'metrics' => $this->metrics->createSeederMetrics($seederClass, $tableName),
                ];
            }
        }

        return $unregisteredSeeders;
    }

    /**
     * Display the final seeding report.
     *
     * @param bool $forceOutput Whether to create a default output if none exists
     * @return string|null The report as a string (if using BufferedOutput)
     */
    public function displayReport(bool $forceOutput = true): ?string
    {
        // Calculate the total execution time
        $totalTime = microtime(true) - $this->startTime;

        // Handle output interface
        $usingBufferedOutput = false;
        $originalOutput = $this->output;

        // Create output if none exists and output is required
        if (!$this->output && $forceOutput) {
            $this->output = new BufferedOutput();
            $this->renderer->setOutput($this->output);
            $usingBufferedOutput = true;
        }

        // If we still don't have an output, use echo to print the report
        if (!$this->output) {
            // For CLI usage, we need to output something even without a proper output interface
            // Create a temporary buffered output to capture the report
            $tempOutput = new BufferedOutput();
            $this->renderer->setOutput($tempOutput);
            $this->renderer->entryPoint(array_merge($this->seeders, $this->unregisteredSeeders), $totalTime);

            // Echo the report directly
            echo $tempOutput->fetch();

            // Restore original state (which was null)
            $this->renderer->setOutput(null);

            return null;
        }

        // Render the report with the configured renderer
        $this->renderer->entryPoint(array_merge($this->seeders, $this->unregisteredSeeders), $totalTime);

        // If using buffered output, fetch and return the content
        if ($usingBufferedOutput && $this->output instanceof BufferedOutput) {
            $report = $this->output->fetch();

            // Also print the report if this isn't being called for capturing
            echo $report;

            // Restore the original output (which was null)
            $this->output = $originalOutput;

            if ($this->output) {
                $this->renderer->setOutput($this->output);
            }

            return $report;
        }

        return null;
    }

    /**
     * Get table name from seeder class name.
     *
     * @param string $seederName
     * @return string
     */
    protected function getTableNameFromSeeder(string $seederName): string
    {
        // Extract the class name without namespace
        $classNameParts = explode('\\', $seederName);
        $className = end($classNameParts);

        // Convert CamelCase to snake_case and remove "Seeder" suffix
        $tableName = preg_replace('/Seeder$/', '', $className);
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tableName));

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
    public function getConfig(): RenderingConfig
    {
        return $this->config;
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
}
