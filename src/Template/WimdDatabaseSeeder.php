<?php

namespace Wimd\Template;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Wimd\Config\RenderingConfig;
use Wimd\Facades\Wimd;
use Wimd\Support\ConsoleFormatter;

/**
 * WimdDatabaseSeeder
 *
 * Abstract base class for orchestrating the execution of multiple WIMD seeders.
 * Provides structured seeder execution with progress reporting, error handling,
 * and performance metrics collection.
 */
abstract class WimdDatabaseSeeder extends Seeder
{
    protected ConsoleFormatter $consoleFormatter;
    protected OutputInterface $outputWriter;
    protected RenderingConfig $config;

    public function __construct()
    {
        $this->consoleFormatter = Wimd::getFormatterInstance();
        $this->outputWriter = new ConsoleOutput();
        $this->config = Wimd::getConfigInstance();
    }

    /**
     * Set a custom output writer for console operations
     *
     * @param OutputInterface $outputWriter
     * @return void
     */
    public function setOutputWriter(OutputInterface $outputWriter): void
    {
        $this->outputWriter = $outputWriter;
    }

    /**
     * Execute one or more seeders with optional parameters
     *
     * @param string|array $class Single class name or array of class names
     * @param bool $silent Whether to suppress output
     * @param array $parameters Parameters to pass to seeders
     * @return void
     */
    public function call($class, $silent = false, array $parameters = []): void
    {
        $classes = Arr::wrap($class);
        Wimd::setSilent($silent);

        foreach ($classes as $seederClass) {
            $this->executeSeeder($seederClass, $silent, $parameters);
        }
    }

    /**
     * Execute a single seeder with error handling
     *
     * @param string $seederClass
     * @param bool $silent
     * @param array $parameters
     * @return void
     */
    protected function executeSeeder(string $seederClass, bool $silent, array $parameters): void
    {
        try {
            $isWimdSeeder = $this->isWimdSeeder($seederClass);
            $this->runSeeder($seederClass, $isWimdSeeder || $silent, $parameters);
        } catch (ReflectionException $e) {
            $this->handleSeederError($seederClass, $e, $silent);
        } catch (\Exception $e) {
            $this->handleSeederError($seederClass, $e, $silent);
        }
    }

    /**
     * Check if a class is a WIMD seeder
     *
     * @param string $class
     * @return bool
     * @throws ReflectionException
     */
    protected function isWimdSeeder(string $class): bool
    {
        $reflection = new ReflectionClass($class);
        $parent = $reflection->getParentClass();

        return $parent && $parent->getShortName() === 'WimdSeeder';
    }

    /**
     * Handle seeder execution errors
     *
     * @param string $seederClass
     * @param \Exception $exception
     * @param bool $silent
     * @return void
     */
    protected function handleSeederError(string $seederClass, \Exception $exception, bool $silent): void
    {
        if (!$silent) {
            $message = sprintf(
                'Failed to execute seeder %s: %s',
                $seederClass,
                $exception->getMessage()
            );
            $this->outputWriter->writeln("  " . $this->consoleFormatter->formatLine($seederClass, "red{ERROR}"));
            $this->outputWriter->writeln("  <error>$message</error>");
        }

        // Log the error for debugging purposes
        \Log::error("Seeder execution failed", [
            'seeder' => $seederClass,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Execute seeders with timing and progress reporting
     *
     * @param string|array $class
     * @param bool $silent
     * @param array $parameters
     * @return static
     */
    public function runSeeder($class, $silent = false, array $parameters = []): static
    {
        $classes = Arr::wrap($class);

        foreach ($classes as $seederClass) {
            $this->executeSingleSeeder($seederClass, $silent, $parameters);
        }

        return $this;
    }

    /**
     * Execute a single seeder with timing and output
     *
     * @param string $seederClass
     * @param bool $silent
     * @param array $parameters
     * @return void
     */
    protected function executeSingleSeeder(string $seederClass, bool $silent, array $parameters): void
    {
        $seeder = $this->resolve($seederClass);
        $className = get_class($seeder);

        if (!$silent) {
            $this->outputRunningStatus($className);
        }

        $executionTime = $this->measureExecutionTime(function() use ($seeder, $parameters) {
            $seeder->__invoke($parameters);
        });

        if (!$silent) {
            $this->outputCompletionStatus($className, $executionTime);
        }

        static::$called[] = $seederClass;
    }

    /**
     * Display running status for a seeder
     *
     * @param string $className
     * @return void
     */
    protected function outputRunningStatus(string $className): void
    {
        $this->outputWriter->writeln(
            "  " . $this->consoleFormatter->formatLine($className, "yellow{RUNNING}")
        );
    }

    /**
     * Display completion status for a seeder
     *
     * @param string $className
     * @param float $executionTime
     * @return void
     */
    protected function outputCompletionStatus(string $className, float $executionTime): void
    {
        $formattedTime = number_format($executionTime, 2);
        $statusMessage = "gray{{$formattedTime} ms} +green{DONE}";

        $this->outputWriter->writeln(
            "  " . $this->consoleFormatter->formatLine($className, $statusMessage)
        );
        $this->outputWriter->writeln("");
    }

    /**
     * Measure execution time of a callback
     *
     * @param callable $callback
     * @return float Execution time in milliseconds
     */
    protected function measureExecutionTime(callable $callback): float
    {
        $startTime = microtime(true);
        $callback();
        return (microtime(true) - $startTime) * 1000;
    }

    /**
     * Display the WIMD report with flexible output options
     *
     * @param OutputInterface|null $output Custom output interface
     * @param bool $returnAsString Whether to return the report as a string
     * @return string|null The report content if requested as string
     */
    public function displayWimdReport(?OutputInterface $output = null, bool $returnAsString = false): ?string
    {
        $reportOutput = $this->prepareReportOutput($output, $returnAsString);

        if ($reportOutput !== null) {
            Wimd::setOutput($reportOutput);
        }

        $result = Wimd::displayReport();

        $filePath = $this->config->getLogFilePath();
        Wimd::writeLog($filePath, "Report processed: " . json_encode($result));

        if (!app()->runningInConsole() || !app()->runningUnitTests()) {
            exit();
        }

        return $result;
    }

    /**
     * Prepare the output interface for report generation
     *
     * @param OutputInterface|null $output
     * @param bool $returnAsString
     * @return OutputInterface|null
     */
    protected function prepareReportOutput(?OutputInterface $output, bool $returnAsString): ?OutputInterface
    {
        if ($output === null && $returnAsString) {
            return new BufferedOutput();
        }

        return $output;
    }
}
