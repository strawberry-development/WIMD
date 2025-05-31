<?php

namespace Wimd\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Wimd\WimdManager setOutput(\Symfony\Component\Console\Output\OutputInterface $output)
 * @method static \Wimd\WimdManager setMode(string $mode)
 * @method static string getMode()
 * @method static void registerSeeder(string $seederClass, array $options = [])
 * @method static array|null getSeederMetrics(string $seederClass)
 * @method static void displayReport()
 * @method static void updateMetrics(string $seederClass, int $recordsAdded, float $executionTime)
 * @method static int getDefaultBatchSize()
 * @method static array getPerformanceThresholds()
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
    protected static function getFacadeAccessor()
    {
        return 'wimd';
    }
}
