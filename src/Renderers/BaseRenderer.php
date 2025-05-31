<?php

namespace Wimd\Renderers;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wimd\config\RenderingConfig;
use Wimd\Support\BorderStyleRegistry;
use Wimd\Support\ConsoleFormatter;
use Wimd\Support\EmojiRegistry;
use Wimd\Support\LaravelConsoleLike;

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

    protected ConsoleFormatter $consoleFormatter;

    /**
     * Constructor
     *
     */
    public function __construct(?RenderingConfig $config = null)
    {
        $this->config = $config ?? new RenderingConfig();

        $this->borders = $this->resolveBorderStyle();
        $this->consoleFormatter = new ConsoleFormatter();
    }

    /**
     * Resolve border style based on configuration
     *
     * @return array
     */
    protected function resolveBorderStyle(): array
    {
        return BorderStyleRegistry::getBorderStyle(
            $this->config->getBorderStyle(),
            $this->config->isUnicodeEnabled()
        );
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
     * @param string $text
     * @return void
     */
    protected function writeOutput(string ...$texts): void
    {
        if (!$this->output) {
            $this->output = new BufferedOutput();
        }

        // Ensure colors are enabled
        $this->output->setDecorated(true);

        foreach ($texts as $text) {
            // Add padding of two spaces to each line
            $paddedText = $this->addPaddingToLines($text);

            // Write using normal output mode to process formatting tags
            $this->output->writeln($paddedText, OutputInterface::OUTPUT_NORMAL);
        }

        // If it's a BufferedOutput, display the contents
        if ($this->output instanceof \Symfony\Component\Console\Output\BufferedOutput) {
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
    public function createTitleBox(string $title = "WIMD SEEDING REPORT"): string
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

    /**
     * Format a labeled value with optional color and emoji
     *
     * @param string $label
     * @param string $value
     * @param string $color
     * @param string $emojiContext
     * @return string
     */
    public function formatLabeledValue(
        string $label,
        string $value,
        string $color = "",
        string $emojiContext = ""
    ): string {
        // Use separate emoji registry for emoji management
        $emoji = $this->config->isEmojisEnabled()
            ? EmojiRegistry::getEmoji($emojiContext)
            : '';

        $emojiPrefix = $emoji ? "{$emoji} " : "";

        if ($color) {
            return "{$emojiPrefix}<fg=white;options=bold>{$label}:</> {$color}{$value}</>";
        }

        return "{$emojiPrefix}<fg=white;options=bold>{$label}:</> {$value}";
    }
}
