<?php

namespace Wimd\Facades;

use Illuminate\Support\Facades\Facade;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Console\Helper\ConsoleFormatter;
use Wimd\Model\DataMetric;
use Wimd\Renderers\ConsoleRenderer;
use Wimd\WimdManager;
use Wimd\Config\RenderingConfig;

/**
 * Wimd Facade
 *
 * This class provides a static interface to the WimdManager service,
 * allowing convenient access to Wimd-related operations such as
 * setting the output, registering seeders, tracking metrics, and
 * generating reports. It simplifies usage by exposing WimdManager's
 * methods through Laravel's facade system.
 */
/**
 * @method static WimdManager setOutput(OutputInterface $output)
 * @method static OutputInterface getOutput()
 * @method static WimdManager setMode(string $mode)
 * @method static WimdManager registerSeeder(string $seederClass, array $options = [])
 * @method static WimdManager updateMetrics(string $seederClass, int $recordsAdded, float $executionTime)
 * @method static string getMode()
 * @method static RenderingConfig getConfigInstance()
 * @method static ConsoleFormatter getFormatterInstance()
 * @method static array|null getSeederMetrics(string $seederClass)
 * @method static array findUnregisteredSeeders(string $seedersPath = null)
 * @method static string displayReport()
 * @method static DataMetric startMonitoring()
 * @method static DataMetric endMonitoring()
 * @method static bool isSilent()
 * @method static WimdManager setSilent(bool $silent)
 * @method static array getMemoryUsage()
 * @method static WimdManager clearCache()
 * @method static void cleanup()
 * @method static ConsoleRenderer getRenderer()
 * @method static void writeLog(string $filePath, string $message)
 *
 * @see \Wimd\WimdManager
 */
class Wimd extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wimd';
    }
}
