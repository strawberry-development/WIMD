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
    }

    public function constructRenderer(): void
    {
        $this->performanceRenderer = new PerformanceRenderer($this->dataMetric);
        $this->dataRenderer = new DataRenderer($this->dataMetric);
        $this->systemRenderer = new SystemRenderer($this->dataMetric);
        $this->recommendationRenderer = new RecommendationRenderer($this->dataMetric);
    }

    /**
     * Render the final seeding report in full detail mode
     *
     * @param DataMetric $dataMetric
     * @return string
     */
    public function renderReport(DataMetric $dataMetric): string
    {
        $this->dataMetric = $dataMetric;
        $this->constructRenderer();

        $displaySetting = $this->config->getDisplaySettings();

        $output = [$this->createTitleBox()];

        // Performance
        $output[] = $this->performanceRenderer->renderMetricsSummary();

        // Performance Charts
        if ($displaySetting['performance_charts'] ?? true) {
            $output[] = $this->performanceRenderer->renderPerformanceDistribution();
            $output[] = $this->performanceRenderer->renderPerformanceChart();
        }

        // Data
        if ($displaySetting['detailed_table'] ?? true) {
            $output[] = $this->dataRenderer->renderDetailedTable();
        }

        // System
        if ($displaySetting['system_info'] ?? true) {
            $output[] = $this->systemRenderer->renderHealthCheck();
            $output[] = $this->systemRenderer->renderSystemInfo();
        }

        // Recommendations
        if ($displaySetting['recommendations'] ?? true) {
            $output[] = $this->recommendationRenderer->renderRecommendations();
        }

        $output[] = $this->createFooter();

        $this->writeOutput(...$output);

        return implode('', $output);
    }
}
