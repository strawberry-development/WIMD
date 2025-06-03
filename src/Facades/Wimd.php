<?php

namespace Wimd\Facades;

use Illuminate\Support\Facades\Facade;
use Wimd\WimdManager;

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
 * @method static WimdManager setOutput(\Symfony\Component\Console\Output\OutputInterface $output)
 * @method static WimdManager setMode(string $mode)
 * @method static WimdManager registerSeeder(string $seederClass, array $options = [])
 * @method static WimdManager updateMetrics(string $seederClass, int $recordsAdded, float $executionTime)
 * @method static string getMode()
 * @method static array|null getSeederMetrics(string $seederClass)
 * @method static array findUnregisteredSeeders(string $seedersPath = null)
 * @method static string|null displayReport(bool $forceOutput = true)
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
