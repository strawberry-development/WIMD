<?php

namespace Wimd\config;

class RenderingConfig
{
    /**
     * Configuration settings
     *
     * @var array
     */
    private array $settings;

    /**
     * Constructor
     *
     * @param array $customSettings Optional custom configuration
     */
    public function __construct(array $customSettings = [])
    {
        // Default configuration matching the config file structure
        $this->settings = array_merge([
            'use_unicode' => true,
            'use_emojis' => true,
            'use_colors' => true,
            'console_width' => 100,
            'border_style' => 'rounded',
            'theme' => [
                'primary' => 'green',
                'secondary' => 'blue',
                'success' => 'green',
                'warning' => 'yellow',
                'danger' => 'red',
                'neutral' => 'white',
                'muted' => 'gray',
            ],
            'progress_format' => [
                'base' => '🍃 [%bar%] %percent:3s%% | ⏳ %elapsed:6s% | ⏱️ %remaining:-6s% | seeding %max%',
                'full' => ' | 🧠 %memory:6s%'
            ]
        ], $customSettings);
    }

    /**
     * Check if Unicode is enabled
     *
     * @return bool
     */
    public function isUnicodeEnabled(): bool
    {
        return $this->settings['use_unicode'];
    }

    /**
     * Check if emojis are enabled
     *
     * @return bool
     */
    public function isEmojisEnabled(): bool
    {
        return $this->settings['use_emojis'];
    }

    /**
     * Check if colors are enabled
     *
     * @return bool
     */
    public function isColorsEnabled(): bool
    {
        return $this->settings['use_colors'];
    }

    /**
     * Get console width
     *
     * @return int
     */
    public function getConsoleWidth(): int
    {
        return $this->settings['console_width'];
    }

    /**
     * Get border style
     *
     * @return string
     */
    public function getBorderStyle(): string
    {
        return $this->settings['border_style'];
    }

    /**
     * Get theme color
     *
     * @param string $key
     * @return string|null
     */
    public function getThemeColor(string $key): ?string
    {
        return $this->settings['theme'][$key] ?? null;
    }

    /**
     * Get entire theme
     *
     * @return array
     */
    public function getTheme(): array
    {
        return $this->settings['theme'];
    }

    /**
     * Get progress format
     *
     * @param string $type Type of progress format ('base' or 'full')
     * @return string
     */
    public function getProgressFormat(string $type = 'base'): string
    {
        return $this->settings['progress_format'][$type] ?? $this->settings['progress_format']['base'];
    }

    /**
     * Get all progress formats
     *
     * @return array
     */
    public function getProgressFormats(): array
    {
        return $this->settings['progress_format'];
    }

    /**
     * Get base progress format
     *
     * @return string
     */
    public function getBaseProgressFormat(): string
    {
        return $this->settings['progress_format']['base'];
    }

    /**
     * Get full progress format (base + additional info)
     *
     * @return string
     */
    public function getFullProgressFormat(): string
    {
        return $this->settings['progress_format']['base'] . $this->settings['progress_format']['full'];
    }

    /**
     * Update a specific setting
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setSetting(string $key, $value): self
    {
        $this->settings[$key] = $value;
        return $this;
    }

    /**
     * Get a specific setting
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Update theme color
     *
     * @param string $key
     * @param string $color
     * @return self
     */
    public function setThemeColor(string $key, string $color): self
    {
        $this->settings['theme'][$key] = $color;
        return $this;
    }

    /**
     * Update progress format
     *
     * @param string $type
     * @param string $format
     * @return self
     */
    public function setProgressFormat(string $type, string $format): self
    {
        $this->settings['progress_format'][$type] = $format;
        return $this;
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        return $this->settings;
    }
}