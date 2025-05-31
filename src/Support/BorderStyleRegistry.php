<?php

namespace Wimd\Support;

class BorderStyleRegistry
{
    /**
     * Predefined border styles
     *
     * @var array<string, array<string, string>>
     */
    private static array $borderStyles = [
        'default' => [
            'top-left' => '+',
            'top-right' => '+',
            'bottom-left' => '+',
            'bottom-right' => '+',
            'horizontal' => '=',
            'vertical' => '|',
            'left-middle' => '+',
            'right-middle' => '+',
            'middle-middle' => '+',
            'top-middle' => '+',
            'bottom-middle' => '+',
            'horizontal-line' => '-',
        ],
        'double' => [
            'top-left' => '╔',
            'top-right' => '╗',
            'bottom-left' => '╚',
            'bottom-right' => '╝',
            'horizontal' => '═',
            'vertical' => '║',
            'left-middle' => '╠',
            'right-middle' => '╣',
            'middle-middle' => '╬',
            'top-middle' => '╦',
            'bottom-middle' => '╩',
            'horizontal-line' => '─',
        ],
        'single' => [
            'top-left' => '┌',
            'top-right' => '┐',
            'bottom-left' => '└',
            'bottom-right' => '┘',
            'horizontal' => '─',
            'vertical' => '│',
            'left-middle' => '├',
            'right-middle' => '┤',
            'middle-middle' => '┼',
            'top-middle' => '┬',
            'bottom-middle' => '┴',
            'horizontal-line' => '─',
        ],
        'rounded' => [
            'top-left' => '╭',
            'top-right' => '╮',
            'bottom-left' => '╰',
            'bottom-right' => '╯',
            'horizontal' => '─',
            'vertical' => '│',
            'left-middle' => '├',
            'right-middle' => '┤',
            'middle-middle' => '┼',
            'top-middle' => '┬',
            'bottom-middle' => '┴',
            'horizontal-line' => '─',
        ],
        'bold' => [
            'top-left' => '┏',
            'top-right' => '┓',
            'bottom-left' => '┗',
            'bottom-right' => '┛',
            'horizontal' => '━',
            'vertical' => '┃',
            'left-middle' => '┣',
            'right-middle' => '┫',
            'middle-middle' => '╋',
            'top-middle' => '┳',
            'bottom-middle' => '┻',
            'horizontal-line' => '━',
        ],
        'block' => [
            'top-left' => '▛',
            'top-right' => '▜',
            'bottom-left' => '▙',
            'bottom-right' => '▟',
            'horizontal' => '▀',
            'vertical' => '▌',
            'left-middle' => '▌',
            'right-middle' => '▐',
            'middle-middle' => '▄',
            'top-middle' => '▀',
            'bottom-middle' => '▄',
            'horizontal-line' => '▄',
        ],
    ];

    /**
     * Get border style
     *
     * @param string $styleName
     * @param bool $useUnicode
     * @return array
     */
    public static function getBorderStyle(string $styleName, bool $useUnicode = true): array
    {
        // If Unicode is disabled, return default non-unicode style
        if (!$useUnicode) {
            return self::$borderStyles['default'];
        }

        // Return specified style or default to 'double'
        return self::$borderStyles[$styleName] ?? self::$borderStyles['double'];
    }

    /**
     * Add a custom border style
     *
     * @param string $name
     * @param array $style
     * @return void
     */
    public static function addBorderStyle(string $name, array $style): void
    {
        self::$borderStyles[$name] = $style;
    }

    /**
     * Get all available border styles
     *
     * @return array
     */
    public static function getAllBorderStyles(): array
    {
        return self::$borderStyles;
    }
}
