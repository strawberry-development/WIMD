<?php

namespace Wimd\Support;

class EmojiRegistry
{
    /**
     * Comprehensive emoji registry
     *
     * @var array<string, string>
     */
    private static array $emojis = [
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'recommendation' => '💡',
    ];

    /**
     * Get an emoji by context
     *
     * @param string $context
     * @return string
     */
    public static function getEmoji(string $context): string
    {
        return self::$emojis[$context] ?? '?';
    }

    /**
     * Get all available emojis
     *
     * @return array<string, string>
     */
    public static function getAllEmojis(): array
    {
        return self::$emojis;
    }

    /**
     * Add a custom emoji
     *
     * @param string $context
     * @param string $emoji
     * @return void
     */
    public static function addEmoji(string $context, string $emoji): void
    {
        self::$emojis[$context] = $emoji;
    }
}
