<?php
namespace Wimd\Renderers\Component;

use Wimd\Model\DataMetric;
use Wimd\Renderers\ConsoleRenderer;

/**
 * RecommendationRenderer is responsible for generating and rendering performance recommendations
 */
class RecommendationRenderer extends Component
{
    /**
     * Render recommendations
     *
     * @return string
     */
    public function renderRecommendations(): string
    {
        $recommendations = $this->generateRecommendations();
        $output = [];

        $output[] = $this->createSectionHeader("RECOMMENDATIONS");

        // Output recommendations
        foreach ($recommendations as $index => $recommendation) {
            $output[] = "<fg=white;options=bold>" . ($index + 1) . ".</> {$recommendation}";
        }

        return implode("\n", $output);
    }

    /**
     * Generate recommendations based on seeding performance
     *
     * @return array
     */
    public function generateRecommendations(): array
    {
        $recommendations = [];
        $seeders = $this->metric->seeders ?? [];

        // Check for slow seeders
        $slowSeeders = array_filter($seeders, function($seeder) {
            return in_array($seeder['metrics']->rating, ['Very Slow', 'Slow']);
        });

        if (count($slowSeeders) > 0) {
            $recommendations[] = "Consider optimizing " . count($slowSeeders) . " slow seeders by using batch inserts";
        }

        // Check for high variance
        if (isset($metrics->maxRecordsPerSecond) && isset($metrics->minRecordsPerSecond) &&
            $metrics->minRecordsPerSecond > 0 &&
            ($metrics->maxRecordsPerSecond / $metrics->minRecordsPerSecond > 5)) {
            $recommendations[] = "Large performance variance detected - consider reviewing the slowest seeders";
        }

        // Check overall performance
        if (isset($metrics->overallRating) && in_array($metrics->overallRating, ['Very Slow', 'Slow'])) {
            $recommendations[] = "Overall performance is below average - consider database indexing improvements";
        }

        // Add specific recommendations
        if (count($seeders) > 10) {
            $recommendations[] = "Large number of seeders detected - consider using parallel seeding if available";
        }

        // If no recommendations, add a positive note
        if (empty($recommendations)) {
            if (isset($metrics->overallRating) && $metrics->overallRating === 'Excellent') {
                $recommendations[] = "Performance looks excellent! No optimizations needed";
            } else {
                $recommendations[] = "No critical issues detected in seeding performance";
            }
        }

        return $recommendations;
    }
}
