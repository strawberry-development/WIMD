<?php

namespace Wimd;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Metrics\MetricsCollector;
use Wimd\Renderers\ConsoleRenderer;
use Wimd\Renderers\RendererInterface;

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

        // Initialize the renderer based on the config
        $this->mode = config('wimd.mode', 'light');
        $this->setMode($this->mode);
    }

    /**
     * Set the output interface.
     *
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->output->setDecorated(true);
        return $this;
    }

    /**
     * Set the mode for WIMD monitoring.
     *
     * @param string $mode 'light' or 'full'
     * @return $this
     */
    public function setMode(string $mode)
    {
        $this->renderer = new ConsoleRenderer();
        $this->mode = $mode;
        return $this;
    }

    /**
     * Register a seeder with the manager.
     *
     * @param string $seederClass
     * @param array $options
     * @return void
     */
    public function registerSeeder(string $seederClass, array $options = [])
    {
        $tableName = $this->getTableNameFromSeeder($seederClass);

        $this->seeders[$seederClass] = [
            'table' => $tableName,
            'options' => $options,
            'metrics' => $this->metrics->createSeederMetrics($seederClass, $tableName),
        ];

        $this->unregisteredSeeders = $this->findUnregisteredSeeders();
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
    public function displayReport(bool $forceOutput = true)
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
     * Update metrics for a seeder.
     *
     * @param string $seederClass
     * @param int $recordsAdded
     * @param float $executionTime
     * @return void
     */
    public function updateMetrics(string $seederClass, int $recordsAdded, float $executionTime)
    {
        if (isset($this->seeders[$seederClass])) {
            $this->metrics->updateSeederMetrics(
                $this->seeders[$seederClass]['metrics'],
                $recordsAdded,
                $executionTime
            );
        }
    }

    /**
     * Get table name from seeder class name.
     *
     * @param string $seederName
     * @return string
     */
    protected function getTableNameFromSeeder(string $seederName)
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
    public function getMode()
    {
        return $this->mode;
    }
}
