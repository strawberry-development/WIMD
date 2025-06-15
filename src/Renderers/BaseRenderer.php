<?php

namespace Wimd\Renderers;

use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Config\RenderingConfig;
use Wimd\Facades\Wimd;
use Wimd\Console\Helper\ConsoleFormatter;

abstract class BaseRenderer implements RendererInterface
{
    /**
     * Console output instance
     */
    protected ?OutputInterface $output = null;

    /**
     * Rendering configuration
     */
    protected RenderingConfig $config;

    /**
     * Border characters
     */
    protected array $borders;

    /**
     * Metrics and performance data
     */
    protected array $metrics = [];
    protected array $seeders = [];
    protected float $totalTime;
    protected bool $isColored;

    protected ConsoleFormatter $consoleFormatter;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->config = Wimd::getConfigInstance();
        $this->consoleFormatter = Wimd::getFormatterInstance();
    }

    /**
     * Set the output interface
     *
     * @param OutputInterface $output
     * @return void
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Safely write output with null check and handling for buffered output
     * Adds padding of two spaces to each line
     *
     * @param string ...$texts
     * @return void
     */
    protected function writeOutput(string ...$texts): void
    {
        $this->output = new ConsoleOutput();

        $this->output->setDecorated(true);

        foreach ($texts as $text) {
            // Add padding of two spaces to each line
            $paddedText = $this->addPaddingToLines($text);

            // Write using normal output mode to process formatting tags
            $this->output->writeln($paddedText, OutputInterface::OUTPUT_NORMAL);
        }

        // If it's a BufferedOutput, display the contents
        if ($this->output instanceof BufferedOutput) {
            // Get the buffered content
            $content = $this->output->fetch();

            // Display it to the console
            echo $content;
        }
    }

    /**
     * Add padding of two spaces to each line of text
     *
     * @param string $text
     * @return string
     */
    protected function addPaddingToLines(string $text): string
    {
        // Split the text into lines
        $lines = explode("\n", $text);

        // Add padding to each line
        $paddedLines = array_map(function($line) {
            return "  " . $line;
        }, $lines);

        // Rejoin the lines
        return implode("\n", $paddedLines);
    }

    /**
     * Create a title box for report headers
     *
     * @param string $title
     * @return string
     */
    /**
     * Create a title box for report headers
     *
     * @param string $title
     * @return string
     */
    public function createTitleBox(string $title = "Wimd seeding report."): string
    {
        return $this->consoleFormatter->bubble($title);
    }

    /**
     * Create a report footer
     *
     * @param string $text
     * @return string
     */
    public function createFooter(string $text = "WIMD report complete — thanks for using!"): string
    {
        return $this->consoleFormatter->bubble($text);
    }


    /**
     * Create a section header
     *
     * @param string $title
     * @return string
     */
    public function createSectionHeader(string $title): string
    {
        return "\n" . $this->consoleFormatter->customBubble($title, "REPORT") . "\n";
    }
}
