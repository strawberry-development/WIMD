<?php

namespace Wimd\Console\Helper;

use Symfony\Component\Console\Output\OutputInterface;
use Wimd\Facades\Wimd;

/**
 * Generic console formatter that handles multiple text segments with automatic spacing
 */
class ConsoleFormatter
{
    /** @var int Default maximum line width */
    private const DEFAULT_LINE_WIDTH = 146;

    /** @var int Minimum line width to prevent overcrowding */
    private const MIN_LINE_WIDTH = 40;

    /** @var string Default filler character */
    private const DEFAULT_FILLER = '.';

    /** @var int padding between filler and content */
    private const PADDING = 1;

    private int $lineWidth;
    private OutputInterface $output;

    public function __construct()
    {
        $this->lineWidth = $this->detectTerminalWidth();
        $this->output = Wimd::getOutput();
    }

    /**
     * Auto-detect terminal width or fall back to default
     */
    private function detectTerminalWidth(): int
    {
        // First try environment variables (works on all platforms)
        $envWidth = getenv('COLUMNS');
        if ($envWidth && is_numeric($envWidth) && $envWidth > self::MIN_LINE_WIDTH) {
            return min((int)$envWidth, self::DEFAULT_LINE_WIDTH);
        }

        // Try to detect terminal width using platform-appropriate commands
        if (function_exists('exec')) {
            $width = null;

            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: Use mode command to get console info
                $output = [];
                $return_var = 0;
                @exec('mode con 2>nul', $output, $return_var);

                if ($return_var === 0) {
                    foreach ($output as $line) {
                        if (preg_match('/Columns:\s*(\d+)/', $line, $matches)) {
                            $width = (int)$matches[1];
                            break;
                        }
                    }
                }
            } else {
                // Unix/Linux/macOS: Use tput command
                $width = @exec('tput cols 2>/dev/null');
            }

            if (is_numeric($width) && $width > self::MIN_LINE_WIDTH) {
                return min((int)$width, self::DEFAULT_LINE_WIDTH);
            }
        }

        // Final fallback to default width
        return self::DEFAULT_LINE_WIDTH;
    }

    /**
     * Enhanced formatLine method that treats each argument as a single segment
     * Arguments are NOT split internally by color formatting
     */
    public function formatLine(...$args): string
    {
        $segments = [];
        $options = [];

        // Extract options if last argument is an array with option keys
        $lastArg = end($args);
        if (is_array($lastArg) && $this->isOptionsArray($lastArg)) {
            $options = array_pop($args);
        }

        // Process remaining arguments as segments - each arg becomes ONE segment
        foreach ($args as $arg) {
            if (is_string($arg) && !is_null($arg)) {
                // Check if this string contains group separators (|)
                if (strpos($arg, '<|>') !== false) {
                    $groups = explode('<|>', $arg);
                    foreach ($groups as $group) {
                        $group = trim($group);
                        if (!empty($group)) {
                            // Each group is treated as a single segment
                            $segments[] = $this->parseArgAsSegment($group);
                        }
                    }
                } else {
                    // Treat entire argument as single segment
                    $segments[] = $this->parseArgAsSegment($arg);
                }
            } elseif (is_array($arg)) {
                // Array format segments
                $segments[] = $arg;
            }
        }

        return $this->formatSegmentsWithGroups($segments, $options);
    }

    /**
     * Parse a single argument as one segment, applying all color formatting within it
     * but not splitting it into multiple segments
     */
    private function parseArgAsSegment(string $input): array
    {
        // Apply color formatting but keep as single segment
        $formattedText = $this->applyInlineColorFormatting($input);

        // Calculate raw length without color tags
        $rawLength = mb_strlen($this->stripTags($formattedText), 'UTF-8');

        return [
            'text' => $formattedText,
            'color' => '', // Already formatted
            'raw_length' => $rawLength
        ];
    }

    /**
     * Apply color formatting to a string without splitting into segments
     * Converts color{text} patterns to proper color tags
     */
    private function applyInlineColorFormatting(string $input): string
    {
        $defaultColor = 'default';

        // Pattern to match optional +, then word, followed by {...}
        $pattern = '/(\+?)(\w+)\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/';

        $result = $input;

        // Store replacements and offsets
        $replacements = [];

        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $matchStart = $match[0][1];
                $matchLength = strlen($fullMatch);

                $isBold = $match[1][0] === '+';
                $color = $match[2][0] ?: $defaultColor;
                $text = $match[3][0];

                if ($isBold) {
                    // For bold colors, use the format: color;options=bold
                    $colorString = "{$color};options=bold";
                } else {
                    // Regular color without bold
                    $colorString = $color;
                }

                $replacement = "<fg={$colorString}>{$text}</>";

                $replacements[] = [
                    'start' => $matchStart,
                    'length' => $matchLength,
                    'replacement' => $replacement,
                ];
            }

            // Apply replacements in reverse order to preserve offsets
            foreach (array_reverse($replacements) as $rep) {
                $result = substr_replace($result, $rep['replacement'], $rep['start'], $rep['length']);
            }
        }

        return $result;
    }

    /**
     * Format segments that may contain grouped adjacent segments
     */
    private function formatSegmentsWithGroups(array $segments, array $options = []): string
    {
        $defaults = [
            'filler' => self::DEFAULT_FILLER,
            'filler_color' => 'gray',
            'distribution' => 'auto',
            'min_filler_between' => 0,
            'newline' => false,
            'align_last_right' => true,
        ];

        $opts = array_merge($defaults, $options);

        if (empty($segments)) {
            return $opts['newline'] ? "\n" : "";
        }

        // Process groups and flatten to calculate spacing
        $processedSegments = [];
        foreach ($segments as $segment) {
            if (is_array($segment) && isset($segment['type']) && $segment['type'] === 'group') {
                // Combine group segments into one segment
                $groupText = '';
                $groupColors = [];

                foreach ($segment['segments'] as $groupSegment) {
                    $normalized = $this->normalizeSegments([$groupSegment])[0];
                    $groupText .= $this->colorText($normalized['text'], $normalized['color']);
                    $groupColors[] = $normalized['color'];
                }

                $processedSegments[] = [
                    'text' => $groupText,
                    'color' => '', // Already colored
                    'raw_length' => mb_strlen($this->stripTags($groupText), 'UTF-8')
                ];
            } else {
                $normalized = $this->normalizeSegments([$segment]);
                if (!empty($normalized)) {
                    $seg = $normalized[0];
                    $processedSegments[] = [
                        'text' => $seg['text'],
                        'color' => $seg['color'],
                        'raw_length' => mb_strlen($this->stripTags($seg['text']), 'UTF-8')
                    ];
                }
            }
        }

        // Calculate total content length
        $totalContentLength = array_sum(array_column($processedSegments, 'raw_length'));

        // Calculate available space for fillers
        $segmentCount = count($processedSegments);
        if ($segmentCount <= 1) {
            $segment = $processedSegments[0] ?? ['text' => '', 'color' => '', 'raw_length' => 0];
            $remainingSpace = max(0, $this->lineWidth - $segment['raw_length']); // Ensure non-negative
            $filler = str_repeat($opts['filler'], $remainingSpace);

            $text = empty($segment['color']) ? $segment['text'] : $this->colorText($segment['text'], $segment['color']);
            return $text . $this->colorText($filler, $opts['filler_color']) . ($opts['newline'] ? "\n" : "");
        }

        $fillerSections = $segmentCount - 1;
        $totalPaddingSpace = $fillerSections * self::PADDING * 2;
        $availableFillerSpace = max(0, $this->lineWidth - $totalContentLength - $totalPaddingSpace); // Ensure non-negative

        // If content already exceeds line width, truncate segments
        if ($totalContentLength + $totalPaddingSpace > $this->lineWidth) {
            $processedSegments = $this->truncateSegmentsToFit($processedSegments, $this->lineWidth - $totalPaddingSpace);
            $totalContentLength = array_sum(array_column($processedSegments, 'raw_length'));
            $availableFillerSpace = 0;
        }

        // Distribute filler space
        $fillerDistribution = $this->calculateFillerDistribution(
            $availableFillerSpace,
            $fillerSections,
            $opts
        );

        // Build the formatted line
        $result = '';
        for ($i = 0; $i < $segmentCount; $i++) {
            $segment = $processedSegments[$i] ?? ['text' => '', 'color' => '', 'raw_length' => 0];

            // Add segment text (already colored if it's a group)
            if (empty($segment['color'])) {
                $result .= $segment['text']; // Already has color formatting
            } else {
                $result .= $this->colorText($segment['text'], $segment['color']);
            }

            // Add filler between segments (except after last segment)
            if ($i < $segmentCount - 1) {
                $fillerCount = $fillerDistribution[$i] ?? max(0, $opts['min_filler_between']);
                $filler = str_repeat(" ", self::PADDING) . str_repeat($opts['filler'], max(0, $fillerCount)) . str_repeat(" ", self::PADDING);
                $result .= $this->colorText($filler, $opts['filler_color']);
            }
        }

        return $result . ($opts['newline'] ? "\n" : "");
    }

    /**
     * Check if array contains option keys rather than segment data
     */
    private function isOptionsArray(array $arr): bool
    {
        $optionKeys = ['filler', 'filler_color', 'distribution', 'min_filler_between', 'newline', 'align_last_right'];
        return !empty(array_intersect(array_keys($arr), $optionKeys)) ||
            (empty($arr) || (!isset($arr[0]) && !isset($arr['text'])));
    }

    /**
     * Format a line with multiple segments automatically spaced
     *
     * @param array $segments Array of segments: [["text", "color"], ["text2", "color2"], ...]
     * @param array $options Formatting options
     * @return string
     */
    public function formatSegments(array $segments, array $options = []): string
    {
        $defaults = [
            'filler' => self::DEFAULT_FILLER,
            'filler_color' => 'gray',
            'distribution' => 'auto', // 'auto', 'even', 'left', 'right', 'center'
            'min_filler_between' => 2,
            'newline' => false,
            'align_last_right' => true, // Last segment aligns to right edge
        ];

        $opts = array_merge($defaults, $options);

        if (empty($segments)) {
            return $opts['newline'] ? "\n" : "";
        }

        // Normalize segments format
        $normalizedSegments = $this->normalizeSegments($segments);

        // Calculate total content length
        $totalContentLength = array_sum(array_map(function ($seg) {
            return mb_strlen($this->stripTags($seg['text']), 'UTF-8');
        }, $normalizedSegments));

        // Calculate available space for fillers
        $segmentCount = count($normalizedSegments);
        $fillerSections = max(1, $segmentCount - 1);
        $totalPaddingSpace = ($segmentCount > 1) ? ($segmentCount - 1) * self::PADDING * 2 : 0;
        $availableFillerSpace = max(0, $this->lineWidth - $totalContentLength - $totalPaddingSpace); // Ensure non-negative

        // Handle single segment case
        if ($segmentCount === 1) {
            $segment = $normalizedSegments[0];
            $segmentLength = mb_strlen($this->stripTags($segment['text']), 'UTF-8');
            $remainingSpace = max(0, $this->lineWidth - $segmentLength); // Ensure non-negative
            $filler = str_repeat($opts['filler'], $remainingSpace);

            return $this->colorText($segment['text'], $segment['color']) .
                $this->colorText($filler, $opts['filler_color']) .
                ($opts['newline'] ? "\n" : "");
        }

        // If content already exceeds line width, truncate segments
        if ($totalContentLength + $totalPaddingSpace > $this->lineWidth) {
            $normalizedSegments = $this->truncateSegmentsToFit($normalizedSegments, $this->lineWidth - $totalPaddingSpace);
            $totalContentLength = array_sum(array_map(function ($seg) {
                return mb_strlen($this->stripTags($seg['text']), 'UTF-8');
            }, $normalizedSegments));
            $availableFillerSpace = 0;
        }

        // Distribute filler space
        $fillerDistribution = $this->calculateFillerDistribution(
            $availableFillerSpace,
            $fillerSections,
            $opts
        );

        // Build the formatted line
        $result = '';
        for ($i = 0; $i < $segmentCount; $i++) {
            $segment = $normalizedSegments[$i] ?? ['text' => '', 'color' => 'default'];
            $result .= $this->colorText($segment['text'], $segment['color']);

            // Add filler between segments (except after last segment)
            if ($i < $segmentCount - 1) {
                $fillerCount = $fillerDistribution[$i] ?? max(0, $opts['min_filler_between']);
                $filler = str_repeat(" ", self::PADDING) . str_repeat($opts['filler'], max(0, $fillerCount)) . str_repeat(" ", self::PADDING);
                $result .= $this->colorText($filler, $opts['filler_color']);
            }
        }

        return $result . ($opts['newline'] ? "\n" : "");
    }

    /**
     * Parse color{text} format string into segments array
     * Default color is white if no color specified
     * Supports bold with + prefix: +color{text} or +{text} for bold white
     */
    private function parseColorString(string $input): array
    {
        $segments = [];
        $defaultColor = 'default';

        // Pattern to match color blocks: word characters (or +) followed by {content}
        // This pattern looks for: start of string OR space OR } followed by optional + and optional color name and {
        $pattern = '/(?:^|\s|(?<=\}))(\+?)([a-zA-Z_]\w*)?(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})/';

        $lastEnd = 0;

        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $matchStart = $match[0][1];
                $isBold = !empty($match[1][0]);
                $color = !empty($match[2][0]) ? $match[2][0] : $defaultColor;
                $textWithBraces = $match[3][0];

                // Remove the outer braces
                $text = substr($textWithBraces, 1, -1);

                // Add any plain text before this match
                if ($matchStart > $lastEnd) {
                    $plainText = substr($input, $lastEnd, $matchStart - $lastEnd);
                    $plainText = ltrim($plainText); // Remove leading space if it exists
                    if ($plainText !== '') {
                        $segments[] = [$plainText, $defaultColor];
                    }
                }

                if ($isBold) {
                    $colorString = "{$color};options=bold";
                } else {
                    $colorString = $color;
                }
                $segments[] = [$text, $colorString];

                $lastEnd = $matchStart + strlen($fullMatch);
            }
        }

        // Add any remaining text after the last match
        if ($lastEnd < strlen($input)) {
            $remainingText = substr($input, $lastEnd);
            $remainingText = ltrim($remainingText); // Remove leading space
            if ($remainingText !== '') {
                $segments[] = [$remainingText, $defaultColor];
            }
        }

        // If no matches found, treat as plain text
        if (empty($segments)) {
            $segments[] = [$input, $defaultColor];
        }

        return $segments;
    }

    /**
     * Normalize segments to consistent format
     */
    private function normalizeSegments(array $segments): array
    {
        $normalized = [];

        foreach ($segments as $segment) {
            if (is_string($segment)) {
                // Simple string - check if it contains color{text} format
                if (strpos($segment, '{') !== false && strpos($segment, '}') !== false) {
                    $parsed = $this->parseColorString($segment);
                    $normalized = array_merge($normalized, $this->normalizeSegments($parsed));
                } else {
                    $normalized[] = ['text' => $segment, 'color' => 'default'];
                }

            } elseif (is_array($segment) && count($segment) >= 2) {
                $color = $segment['color'];

                // Fix +color format by converting to proper bold syntax
                if (is_string($color) && strpos($color, '+') === 0) {
                    $actualColor = substr($color, 1); // Remove the + prefix
                    $color = $actualColor === '' ? 'default;options=bold' : "{$actualColor};options=bold";
                }

                $normalized[] = [
                    'text' => (string)$segment['text'],
                    'color' => $color
                ];
            } elseif (is_array($segment) && isset($segment['text'])) {
                $color = $segment['color'] ?? 'default';

                // Fix +color format by converting to proper bold syntax
                if (is_string($color) && strpos($color, '+') === 0) {
                    $actualColor = substr($color, 1); // Remove the + prefix
                    $color = $actualColor === '' ? 'default;options=bold' : "{$actualColor};options=bold";
                }

                $normalized[] = [
                    'text' => (string)$segment['text'],
                    'color' => $color
                ];
            }
        }

        return $normalized;
    }

    /**
     * Calculate how to distribute filler space between segments
     */
    private function calculateFillerDistribution(int $totalSpace, int $sections, array $opts): array
    {
        if ($totalSpace <= 0 || $sections <= 0) {
            return array_fill(0, max(1, $sections), max(0, $opts['min_filler_between']));
        }

        $distribution = [];
        $minFiller = max(0, $opts['min_filler_between']);

        switch ($opts['distribution']) {
            case 'even':
                // Distribute evenly
                $baseAmount = max($minFiller, intval($totalSpace / $sections));
                $remainder = max(0, $totalSpace % $sections);

                for ($i = 0; $i < $sections; $i++) {
                    $distribution[$i] = $baseAmount + ($i < $remainder ? 1 : 0);
                }
                break;

            case 'left':
                // Most space at the beginning
                $distribution[0] = max($minFiller, $totalSpace - ($sections - 1) * $minFiller);
                for ($i = 1; $i < $sections; $i++) {
                    $distribution[$i] = $minFiller;
                }
                break;

            case 'right':
                // Most space at the end
                for ($i = 0; $i < $sections - 1; $i++) {
                    $distribution[$i] = $minFiller;
                }
                $distribution[$sections - 1] = max($minFiller, $totalSpace - ($sections - 1) * $minFiller);
                break;

            case 'center':
                // Most space in the middle
                $middleIndex = intval($sections / 2);
                $middleSpace = max($minFiller, $totalSpace - ($sections - 1) * $minFiller);

                for ($i = 0; $i < $sections; $i++) {
                    $distribution[$i] = $i === $middleIndex ? $middleSpace : $minFiller;
                }
                break;

            case 'auto':
            default:
                // Smart distribution: more space between distant elements
                if ($sections === 1) {
                    $distribution[0] = max(0, $totalSpace);
                } elseif ($sections === 2) {
                    // Two segments: most space between them
                    $distribution[0] = max(0, $totalSpace);
                } else {
                    // Multiple segments: distribute with preference for larger gaps
                    $baseAmount = max($minFiller, intval($totalSpace / $sections));
                    $remainder = max(0, $totalSpace - ($baseAmount * $sections));

                    for ($i = 0; $i < $sections; $i++) {
                        $distribution[$i] = $baseAmount;
                    }

                    // Distribute remainder to middle sections
                    $middleStart = intval($sections / 3);
                    for ($i = 0; $i < $remainder; $i++) {
                        $targetIndex = ($middleStart + $i) % $sections;
                        $distribution[$targetIndex]++;
                    }
                }
                break;
        }

        // Final safety check - ensure no negative values and total doesn't exceed totalSpace
        $actualTotal = 0;
        foreach ($distribution as $i => $value) {
            $distribution[$i] = max(0, $value);
            $actualTotal += $distribution[$i];
        }

        // If somehow we exceeded totalSpace, proportionally reduce
        if ($actualTotal > $totalSpace) {
            $ratio = $totalSpace / $actualTotal;
            foreach ($distribution as $i => $value) {
                $distribution[$i] = max(0, intval($value * $ratio));
            }
        }

        return $distribution;
    }

    /**
     * Apply color formatting to text
     */
    private function colorText(string $text, ?string $color): string
    {
        if (empty($color) || empty($text)) {
            return $text;
        }

        $color = $color ?? 'default';

        if (strpos($color, '+') === 0) {
            $actualColor = substr($color, 1); // Remove the + prefix
            $color = $actualColor === '' ? 'default;options=bold' : "{$actualColor};options=bold";
        }

        // Handle complex color definitions (e.g., "white;bg=red;options=bold")
        return "<fg={$color}>{$text}</>";
    }

    /**
     * Quick helper for common two-segment format (like your original example)
     */
    public function formatTwoSegments(string $leftText, string $leftColor, string $rightText, string $rightColor, array $options = []): string
    {
        return $this->formatSegments([
            [$leftText, $leftColor],
            [$rightText, $rightColor]
        ], $options);
    }

    /**
     * Process multiple lines with different segment configurations
     */
    public function formatMultipleLines(array $lines, array $globalOptions = []): array
    {
        $results = [];

        foreach ($lines as $line) {
            if (isset($line['segments'])) {
                $lineOptions = array_merge($globalOptions, $line['options'] ?? []);
                $results[] = $this->formatSegments($line['segments'], $lineOptions);
            } elseif (is_array($line)) {
                // Assume it's a segments array
                $results[] = $this->formatSegments($line, $globalOptions);
            }
        }

        return $results;
    }


    /**
     * Strip formatting tags for length calculation
     */
    private function stripTags(string $text): string
    {
        return preg_replace('/<[^>]*>/', '', $text);
    }

    /**
     * Creates a bubble-style message
     */
    public function bubble(string $text, string $type = 'info', array $options = []): string
    {
        $types = [
            'info' => ['bg' => 'blue', 'fg' => 'white'],
            'error' => ['bg' => 'red', 'fg' => 'white'],
            'warning' => ['bg' => 'yellow', 'fg' => 'black'],
            'success' => ['bg' => 'green', 'fg' => 'white'],
        ];

        $config = $types[$type] ?? $types['info'];
        $config = array_merge($config, $options);

        $label = strtoupper($type);
        return "<fg={$config['fg']};bg={$config['bg']};options=bold> {$label} </> {$text}";
    }

    /**
     * Custom bubble with specified colors and label
     */
    public function customBubble(string $text, string $label, array $colors = []): string
    {
        $bg = $colors['bg'] ?? 'blue';
        $fg = $colors['fg'] ?? 'white';
        $labelUpper = strtoupper($label);

        return "<fg={$fg};bg={$bg};options=bold> {$labelUpper} </> {$text}";
    }

    /**
     * Set line width manually
     */
    public function setLineWidth(int $width): self
    {
        $this->lineWidth = max(self::MIN_LINE_WIDTH, $width);
        return $this;
    }

    /**
     * Get current line width
     */
    public function getLineWidth(): int
    {
        return $this->lineWidth;
    }

    /**
     * Format a string to a constant width, padding with filler character or truncating as needed
     *
     * @param string $text The text to format
     * @param int $width The desired constant width
     * @param string|null $color The color to apply to the text (e.g., 'red', 'blue', 'green;options=bold')
     * @param string|null $filler The character to use for padding (defaults to class constant)
     * @param string $align Alignment: 'left', 'right', or 'center' (default: 'left')
     * @return string The formatted string with constant width
     */
    public function constantWidth(string $text, int $width, ?string $color = null, ?string $filler = null, string $align = 'left'): string
    {
        if ($filler === null) {
            $filler = self::DEFAULT_FILLER;
        }

        // Apply color to text if specified
        $coloredText = $color ? $this->colorText($text, $color) : $text;

        // Calculate the actual display length (without color tags)
        $displayText = $this->stripTags($coloredText);
        $currentLength = mb_strlen($displayText, 'UTF-8');

        // If text is longer than width, truncate it
        if ($currentLength > $width) {
            // Find where to cut in the original text to preserve color formatting
            $truncated = $this->truncatePreservingColors($coloredText, $width);
            return $truncated;
        }

        // If text is shorter, pad it
        $paddingNeeded = $width - $currentLength;
        $fillerText = $this->colorText(str_repeat($filler, $paddingNeeded), 'gray');

        switch ($align) {
            case 'right':
                return $fillerText . $coloredText;

            case 'center':
                $leftPadding = intval($paddingNeeded / 2);
                $rightPadding = $paddingNeeded - $leftPadding;
                $leftFillerText = $this->colorText(str_repeat($filler, $leftPadding), 'gray');
                $rightFillerText = $this->colorText(str_repeat($filler, $rightPadding), 'gray');
                return $leftFillerText . $coloredText . $rightFillerText;

            case 'left':
            default:
                return $coloredText . $fillerText;
        }
    }

    /**
     * Helper method to truncate text while preserving color formatting
     */
    private function truncatePreservingColors(string $text, int $maxLength): string
    {
        $result = '';
        $currentLength = 0;
        $inTag = false;
        $tagBuffer = '';

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];

            if ($char === '<') {
                $inTag = true;
                $tagBuffer = '<';
            } elseif ($char === '>' && $inTag) {
                $inTag = false;
                $result .= $tagBuffer . '>';
                $tagBuffer = '';
            } elseif ($inTag) {
                $tagBuffer .= $char;
            } else {
                if ($currentLength >= $maxLength) {
                    break;
                }
                $result .= $char;
                $currentLength++;
            }
        }

        return $result;
    }

    /**
     * Truncate segments when they exceed available space to ensure line width is never exceeded
     *
     * @param array $segments Array of segments with 'text', 'color', and 'raw_length' keys
     * @param int $maxTotalLength Maximum total length allowed for all segments combined
     * @return array Truncated segments that fit within the specified length
     */
    private function truncateSegmentsToFit(array $segments, int $maxTotalLength): array
    {
        if ($maxTotalLength <= 0) {
            return [];
        }

        $totalLength = array_sum(array_column($segments, 'raw_length'));

        if ($totalLength <= $maxTotalLength) {
            return $segments;
        }

        // Proportionally reduce each segment
        $truncatedSegments = [];
        $remainingLength = $maxTotalLength;

        foreach ($segments as $i => $segment) {
            if ($remainingLength <= 0) {
                break;
            }

            $targetLength = min($segment['raw_length'], $remainingLength);

            if ($targetLength < $segment['raw_length']) {
                // Truncate this segment
                $truncatedText = $this->truncatePreservingColors(
                    empty($segment['color']) ? $segment['text'] : $this->colorText($segment['text'], $segment['color']),
                    $targetLength
                );

                $truncatedSegments[] = [
                    'text' => $truncatedText,
                    'color' => '', // Already colored if needed
                    'raw_length' => $targetLength
                ];
            } else {
                $truncatedSegments[] = $segment;
            }

            $remainingLength -= $targetLength;
        }

        return $truncatedSegments;
    }


}
