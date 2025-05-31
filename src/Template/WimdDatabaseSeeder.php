<?php

namespace Wimd\Template;

use Illuminate\Database\Seeder;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Facades\Wimd;

/**
 * Abstract base class for all WIMD seeders with advanced progress tracking and batch processing.
 */
abstract class WimdDatabaseSeeder extends Seeder
{
    public function call($class, $silent = false, array $parameters = []): void
    {
        parent::call($class, true, $parameters);
    }

    /**
     * Display the WIMD report.
     *
     * @param OutputInterface|null $output Custom output interface (optional)
     * @param bool $returnAsString Whether to return the report as a string
     * @return string|null The report as a string (if $returnAsString is true)
     */
    public function displayWimdReport(?OutputInterface $output = null, bool $returnAsString = false): ?string
    {
        // If no output is provided and we want a string return,
        // use a buffered output
        if ($output === null && $returnAsString) {
            $output = new BufferedOutput();
        }

        // If output is provided, set it in the WIMD manager
        if ($output !== null) {
            Wimd::setOutput($output);
        }

        // Display the report
        Wimd::displayReport();

        // If using buffered output, return the content as string
        if ($returnAsString && $output instanceof BufferedOutput) {
            $output->writeln($output->fetch());
        }

        // End of the package, make sure nothing happen after
        exit();
    }

}
