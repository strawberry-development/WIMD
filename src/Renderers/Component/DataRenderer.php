<?php

namespace Wimd\Renderers\Component;

use Wimd\Model\DataMetric;

/**
 * TableRenderer is responsible for rendering detailed table visualizations
 */
class DataRenderer extends Component
{
    /**
     * Render the detailed table of seeders
     *
     * @return string
     */
    public function renderDetailedTable(): string
    {
        $output = [];

        $output[] = $this->createSectionHeader("Detailed seeder metrics.");

        // Sort results by records per second (descending)
        uasort($this->metric->seeders, function ($a, $b) {
            return $b['metrics']->recordsPerSecond <=> $a['metrics']->recordsPerSecond;
        });

        $width = 28;

        // Draw table header
        $output[] = $this->consoleFormatter->formatLine(
            $this->consoleFormatter->constantWidth("Seeder", $width),
            $this->consoleFormatter->constantWidth("Records", $width),
            $this->consoleFormatter->constantWidth("Time (sec)", $width),
            $this->consoleFormatter->constantWidth("Records/sec", $width),
            $this->consoleFormatter->constantWidth("Rating", $width - 5),
        );

        // Process each seeder
        $rank = 1;
        $totalSeeders = count($this->metric->seeders);
        $rankingColor = ['+green', 'green', 'yellow', 'red', '+red'];

        foreach ($this->metric->seeders as $seederClass => $seeder) {
            $metrics = $seeder['metrics'];
            $seederName = $metrics->getShortName();
            $ratingColor = $this->metric->getRatingColor($metrics->rating);
            $ratingDisplay = $metrics->rating;

            // Calculate position-based color
            $positionIndex = min(4, floor(($rank - 1) / ($totalSeeders / 5)));
            $positionColor = $rankingColor[$positionIndex];

            // Add rank indicator
            $rankIndicator = ($rank <= 3) ? "#$rank " : "";

            $output[] = $this->consoleFormatter->formatLine(
                $this->consoleFormatter->constantWidth($rankIndicator . $seederName, $width, $positionColor),
                $this->consoleFormatter->constantWidth(number_format($metrics->recordsAdded), $width),
                $this->consoleFormatter->constantWidth(round($metrics->executionTime, 4), $width),
                $this->consoleFormatter->constantWidth($metrics->recordsPerSecond, $width),
                $this->consoleFormatter->constantWidth($ratingDisplay, $width - 5, $ratingColor),
            );

            $rank++;
        }

        return implode("\n", $output);
    }
}
