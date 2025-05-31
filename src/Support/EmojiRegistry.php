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
        'time' => '⏱️',
        'records' => '📊',
        'performance' => '⚡',
        'rating' => '🏆',
        'fastest' => '🚀',
        'slowest' => '🐢',
        'database' => '💾',
        'environment' => '🔧',
        'memory' => '🧠',
        'clock' => '⏰',
        'seeding' => '🌱',
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'info' => 'ℹ️',
        'debug' => '🔍',
        'critical' => '🔥',
        'alert' => '🚨',
        'emergency' => '🆘',
        'sparkles' => '✨',
        'chart' => '📈',
        'notice' => '📝',
        'server' => '🖥️',
        'cache' => '⚡',
        'user' => '👤',
        'users' => '👥',
        'lock' => '🔒',
        'unlock' => '🔓',
        'calendar' => '📅',
        'config' => '⚙️',
        'mail' => '📧',
        'search' => '🔎',
        'cloud' => '☁️',
        'download' => '⬇️',
        'upload' => '⬆️',
        'sync' => '🔄',
        'trash' => '🗑️',
        'edit' => '✏️',
        'save' => '💾',
        'refresh' => '🔄',
        'code' => '💻',
        'terminal' => '🖥️',
        'php' => '🐘',
        'sql' => '🗃️',
        'api' => '🔌',
        'queue' => '📦',
        'loading' => '⏳',
        'complete' => '🏁',
        'pending' => '⏳',
        'running' => '▶️',
        'stopped' => '⏹️',
        'recommendation' => '💡',
        'laravel' => '⚙️'
    ];

    /**
     * Get an emoji by context
     *
     * @param string $context
     * @return string
     */
    public static function getEmoji(string $context): string
    {
        return self::$emojis[$context] ?? '[?]';
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
