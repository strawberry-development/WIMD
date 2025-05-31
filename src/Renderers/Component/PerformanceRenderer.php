<?php
namespace Wimd\Renderers\Component;

use Wimd\Model\DataMetric;
use Wimd\Support\EmojiRegistry;

/**
 * PerformanceRenderer is responsible for rendering performance-related visualizations
 */
class PerformanceRenderer extends Component
{
    /**
     * Render performance distribution chart from DataMetric
     *
     * @return string
     */
    public function renderPerformanceDistribution(): string
    {
        $distribution = $this->metric->getPerformanceDistributionArray();

        if (empty($distribution)) {
            return '';
        }

        $output = [];
        $output[] = $this->createSectionHeader("PERFORMANCE DISTRIBUTION");

        $maxChars = 60;
        $nameWidth = 25;

        $total = $this->metric->seeders_count;

        $categories = [
            'Excellent' => $distribution['excellent'] ?? 0,
            'Good' => $distribution['good'] ?? 0,
            'Average' => $distribution['average'] ?? 0,
            'Poor' => $distribution['poor'] ?? 0,
            'Critical' => $distribution['critical'] ?? 0
        ];

        // Remove empty categories
        $categories = array_filter($categories);

        if (empty($categories)) {
            return '';
        }

        foreach ($categories as $category => $count) {
            $percentage = ($count / $total) * 100;
            $barLength = round(($count / $total) * $maxChars);

            // Determine color based on category
            $color = match(strtolower($category)) {
                'excellent' => '+green',
                'good' => 'green',
                'average' => 'yellow',
                'poor' => 'red',
                'critical' => '+red',
                default => 'white'
            };

            $bar = str_repeat('■', $barLength);

            $output[] = $this->consoleFormatter->formatLine($this->consoleFormatter->fillWithChar("$category ", $nameWidth) . " {$color}{{$bar}}", "$percentage% ($count seeders)");

           /* $output[] = sprintf(
                "%-10s %s%-40s</> %3d%% (%d seeders)",
                $category,
                $color,
                $bar . str_repeat(' ', $maxChars - $barLength),
                $percentage,
                $count
            );*/
        }

        return implode("\n", $output);
    }

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

        $output[] = $this->consoleFormatter->formatLine("Execution Time",  "{$this->metric->formatted_time}");
        $output[] = $this->consoleFormatter->formatLine("Records Added gray{(~{$this->metric->time_per_record} ms/record)}",  "{$this->metric->total_records}");
        $output[] = $this->consoleFormatter->formatLine("Overall Speed",  "{$this->metric->records_per_second} records/second");
        $output[] = $this->consoleFormatter->formatLine("Performance Rating",  "{$this->metric->rating_color}{{$this->metric->overall_rating}}");

        // Show seeder metrics with more context

        $output[] = $this->consoleFormatter->formatLine("Fastest Seeder gray{({$this->metric->max_records_per_second} records/sec)}",  "{$this->metric->fastColor}{{$this->metric->fastest_seeder}}");
        $output[] = $this->consoleFormatter->formatLine("Slowest Seeder gray{({$this->metric->min_records_per_second} records/sec)}",  "{$this->metric->slowColor}{{$this->metric->slowest_seeder}}");
        $output[] = $this->consoleFormatter->formatLine("Speed Variance",  "+default{{$this->metric->speed_variance}%} difference between fastest and slowest");

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
        uasort($this->metric->seeders, function($a, $b) {
            return $b['metrics']->recordsPerSecond <=> $a['metrics']->recordsPerSecond;
        });
        // Take top 5 for clarity
        $seeders = array_slice($this->metric->seeders, 0, 5, true);
        $output[] = $this->createSectionHeader("TOP SEEDERS BY PERFORMANCE");

        $chartWidth = 60; // Slightly increased for better visualization
        $nameWidth = 25;  // Name width from original

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

            $bar = $bar . str_repeat('.', $chartWidth - $barLength);
            $output[] = $this->consoleFormatter->formatLine($this->consoleFormatter->fillWithChar("$seederName ", $nameWidth) . " {$color}{{$bar}}", "$formattedValue records/second");
        }

        return implode(PHP_EOL, $output);
    }
}
