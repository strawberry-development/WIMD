<?php

namespace Wimd\Renderers;

use Wimd\Metrics\MetricsCollector;
use Wimd\Model\DataMetric;
use Wimd\Renderers\Component\PerformanceRenderer;
use Wimd\Renderers\Component\RecommendationRenderer;
use Wimd\Renderers\Component\SystemRenderer;
use Wimd\Renderers\Component\DataRenderer;
use function PHPUnit\Framework\isEmpty;

class ConsoleRenderer extends BaseRenderer
{
    protected array $metrics = [];
    protected array $seeders = [];
    protected float $totalTime;

    // Component renderers
    protected PerformanceRenderer $performanceRenderer;
    protected DataRenderer $dataRenderer;
    protected SystemRenderer $systemRenderer;
    protected RecommendationRenderer $recommendationRenderer;
    protected DataMetric $dataMetric;

    /**
     * Metrics collector instance
     */
    protected MetricsCollector $metricsCollector;

    public function __construct()
    {
        parent::__construct();
        $this->metricsCollector = new MetricsCollector();
        $this->dataMetric = new DataMetric();
    }

    public function constructRenderer(): void
    {
        $this->performanceRenderer = new PerformanceRenderer($this->dataMetric);
        $this->dataRenderer = new DataRenderer($this->dataMetric);
        $this->systemRenderer = new SystemRenderer($this->dataMetric);
        $this->recommendationRenderer = new RecommendationRenderer($this->dataMetric);
    }

    /**
     * Main entry point for rendering the seeding report
     *
     * @param array $seeders
     * @param float $totalTime
     * @return void
     */
    public function entryPoint(array $seeders, float $totalTime): void

    {
        $this->totalTime = round($totalTime, 4);
        $this->seeders = $seeders;

        // Create data metrics
        $this->dataMetric->createFromSeeders(
            $this->seeders,
            $this->totalTime,
            $this->metricsCollector
        );

        $this->constructRenderer();

        // Render the full report
        $this->renderReport();
    }

    /**
     * Render the final seeding report in full detail mode
     *
     * @return void
     */
    public function renderReport(): void
    {
        $this->writeOutput(
            $this->createTitleBox(),

            // Performance
            $this->performanceRenderer->renderMetricsSummary(),
            $this->performanceRenderer->renderPerformanceDistribution(),
            $this->performanceRenderer->renderPerformanceChart(),

            // Data
            $this->dataRenderer->renderDetailedTable(),

            // System
            $this->systemRenderer->renderHealthCheck(),
            $this->systemRenderer->renderSystemInfo(),

            // Recommendation
            $this->recommendationRenderer->renderRecommendations(),

            "\n",
            $this->createFooter()
        );
    }
}
