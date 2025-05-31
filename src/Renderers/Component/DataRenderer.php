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

        $output[] = $this->createSectionHeader("DETAILED SEEDER METRICS");

        // Sort results by records per second (descending)
        uasort($this->metric->seeders, function($a, $b) {
            return $b['metrics']->recordsPerSecond <=> $a['metrics']->recordsPerSecond;
        });

        // Draw table header
        $output[] = $this->consoleFormatter->formatLine("Seeder Records Time (sec) Records/sec Rating");

        // Process each seeder
        $rank = 1;
        $totalSeeders = count($this->metric->seeders);
        $rankingColor = ['<fg=green;options=bold>', '<fg=green>', '<fg=yellow>', '<fg=red>', '<fg=red;options=bold>'];

        foreach ($this->metric->seeders as $seederClass => $seeder) {
            $metrics = $seeder['metrics'];
            $seederName = $metrics->getShortName();
            $ratingColor = $this->metric->getRatingColor($metrics->rating);
            // WIP
            $ratingDisplay = $metrics->rating;
            // $ratingDisplay = "{$ratingColor}{{$metrics->rating}}";

            // Calculate position-based color
            $positionIndex = min(4, floor(($rank - 1) / ($totalSeeders / 5)));
            $positionColor = $rankingColor[$positionIndex];

            // Add rank indicator
            $rankIndicator = ($rank <= 3) ? "#$rank " : "";

            $output[] = sprintf(
                "{$positionColor}%-30s</> %-15s %-12s %-15s %-10s",
                $rankIndicator . $seederName,
                number_format($metrics->recordsAdded),
                round($metrics->executionTime, 4),
                $metrics->recordsPerSecond,
                $ratingDisplay
            );

            $rank++;
        }

        return implode("\n", $output);
    }
}
