<?php

namespace Wimd\Renderers\Component;

use Wimd\Model\DataMetric;

/**
 * PerformanceRenderer is responsible for rendering performance-related visualizations
 */
class PerformanceRenderer extends Component
{
    /**
     * Render a performance summary using DataMetric
     *
     * @param DataMetric $dataMetric
     * @return string
     */
    public function renderMetricsSummary(): string
    {
        $output = [];

        $output[] = $this->createSectionHeader("PERFORMANCE SUMMARY");

        $output[] = $this->consoleFormatter->formatLine("Execution Time", "{$this->metric->formatted_time}");
        $output[] = $this->consoleFormatter->formatLine("Records Added gray{(~{$this->metric->time_per_record} ms/record)}", "{$this->metric->total_records}");
        $output[] = $this->consoleFormatter->formatLine("Overall Speed", "{$this->metric->records_per_second} records/second");
        $output[] = $this->consoleFormatter->formatLine("Performance Rating", "{$this->metric->rating_color}{{$this->metric->overall_rating}}");

        // Show seeder metrics with more context

        $output[] = $this->consoleFormatter->formatLine("Fastest Seeder gray{({$this->metric->max_records_per_second} records/sec)}", "{$this->metric->fastColor}{{$this->metric->fastest_seeder}}");
        $output[] = $this->consoleFormatter->formatLine("Slowest Seeder gray{({$this->metric->min_records_per_second} records/sec)}", "{$this->metric->slowColor}{{$this->metric->slowest_seeder}}");
        $output[] = $this->consoleFormatter->formatLine("Speed Variance", "+default{{$this->metric->speed_variance}%} difference between fastest and slowest");

        return implode("\n", $output);
    }

    /**
     * Render a performance chart
     *
     * @param DataMetric $dataMetric
     * @param array $seeders Detailed seeder information
     * @return string
     */
    public function renderPerformanceChart(): string
    {
        if (count($this->metric->seeders) < 2) {
            return '';
        }
        $output = [];
        // Sort by performance (descending)
        uasort($this->metric->seeders, function ($a, $b) {
            return $b['metrics']->recordsPerSecond <=> $a['metrics']->recordsPerSecond;
        });
        // Take top 5 for clarity
        $seeders = array_slice($this->metric->seeders, 0, 5, true);
        $output[] = $this->createSectionHeader("TOP SEEDERS BY PERFORMANCE");

        $chartWidth = 60; // Slightly increased for better visualization
        $nameWidth = 28;  // Name width from original

        $maxValue = $this->metric->max_records_per_second;
        foreach ($seeders as $seederClass => $seeder) {
            $metrics = $seeder['metrics'];
            $seederName = $metrics->getShortName();
            $value = $metrics->recordsPerSecond;
            $barLength = round(($value / $maxValue) * $chartWidth);

            // Get color based on rating
            $color = $this->metric->getRatingColor($metrics->rating);
            $bar = str_repeat('■', $barLength);

            // Format values
            $formattedValue = number_format($value, 2);

            $output[] = $this->consoleFormatter->formatLine(
                $this->consoleFormatter->constantWidth($seederName, $nameWidth),
                $this->consoleFormatter->constantWidth($bar, $chartWidth, $color),
                $this->consoleFormatter->constantWidth("$formattedValue records/second", 54, null, null, 'right'),
            );
        }

        return implode(PHP_EOL, $output);
    }
}
