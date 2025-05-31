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
     * @param array $customSettings Optional custom configuration to override config file values
     */
    public function __construct(array $customSettings = [])
    {
        // Load configuration from Laravel config file
        $configFromFile = config('wimd', []);

        // Merge with custom settings, giving priority to custom settings
        $this->settings = array_merge($configFromFile, $customSettings);
    }

    /**
     * Get the display mode
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->settings['mode'] ?? 'full';
    }

    /**
     * Set the display mode
     *
     * @param string $mode
     * @return self
     */
    public function setMode(string $mode): self
    {
        $this->settings['mode'] = $mode;
        return $this;
    }

    /**
     * Check if a display component is enabled
     *
     * @param string $component
     * @return bool
     */
    public function isDisplayEnabled(string $component): bool
    {
        return $this->settings['display'][$component] ?? false;
    }

    /**
     * Get all display settings
     *
     * @return array
     */
    public function getDisplaySettings(): array
    {
        return $this->settings['display'] ?? [];
    }

    /**
     * Update display setting
     *
     * @param string $component
     * @param bool $enabled
     * @return self
     */
    public function setDisplayEnabled(string $component, bool $enabled): self
    {
        $this->settings['display'][$component] = $enabled;
        return $this;
    }

    /**
     * Check if Unicode is enabled
     *
     * @return bool
     */
    public function isUnicodeEnabled(): bool
    {
        return $this->settings['styling']['use_unicode'] ?? true;
    }

    /**
     * Check if emojis are enabled
     *
     * @return bool
     */
    public function isEmojisEnabled(): bool
    {
        return $this->settings['styling']['use_emojis'] ?? true;
    }

    /**
     * Check if colors are enabled
     *
     * @return bool
     */
    public function isColorsEnabled(): bool
    {
        return $this->settings['styling']['use_colors'] ?? true;
    }

    /**
     * Get border style (marked as obsolete in config)
     *
     * @return string
     */
    public function getBorderStyle(): string
    {
        return $this->settings['styling']['border_style'] ?? 'rounded';
    }

    /**
     * Get styling settings
     *
     * @return array
     */
    public function getStylingSettings(): array
    {
        return $this->settings['styling'] ?? [];
    }

    /**
     * Get progress format
     *
     * @param string $type Type of progress format ('bar', 'base', or 'full')
     * @return string
     */
    public function getProgressFormat(string $type = 'base'): string
    {
        $formats = $this->settings['styling']['progress_format'] ?? [];
        return $formats[$type] ?? $formats['base'] ?? '[%bar%] %percent:3s%%';
    }

    /**
     * Get all progress formats
     *
     * @return array
     */
    public function getProgressFormats(): array
    {
        return $this->settings['styling']['progress_format'] ?? [];
    }

    /**
     * Get base progress format
     *
     * @return string
     */
    public function getBaseProgressFormat(): string
    {
        return $this->getProgressFormat('base');
    }

    /**
     * Get full progress format (base + additional info)
     *
     * @return string
     */
    public function getFullProgressFormat(): string
    {
        $base = $this->getProgressFormat('base');
        $full = $this->getProgressFormat('full');
        return $base . $full;
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
        $this->settings['styling']['progress_format'][$type] = $format;
        return $this;
    }

    /**
     * Get performance threshold
     *
     * @param string $level
     * @return int|null
     */
    public function getPerformanceThreshold(string $level): ?int
    {
        return $this->settings['thresholds'][$level] ?? null;
    }

    /**
     * Get all performance thresholds
     *
     * @return array
     */
    public function getPerformanceThresholds(): array
    {
        return $this->settings['thresholds'] ?? [];
    }

    /**
     * Set performance threshold
     *
     * @param string $level
     * @param int $threshold
     * @return self
     */
    public function setPerformanceThreshold(string $level, int $threshold): self
    {
        $this->settings['thresholds'][$level] = $threshold;
        return $this;
    }

    /**
     * Check if memory warnings are enabled
     *
     * @return bool
     */
    public function areMemoryWarningsEnabled(): bool
    {
        return $this->settings['memory']['warnings_enabled'] ?? true;
    }

    /**
     * Get memory threshold
     *
     * @param string $level
     * @return string|null
     */
    public function getMemoryThreshold(string $level): ?string
    {
        return $this->settings['memory']['thresholds'][$level] ?? null;
    }

    /**
     * Get all memory thresholds
     *
     * @return array
     */
    public function getMemoryThresholds(): array
    {
        return $this->settings['memory']['thresholds'] ?? [];
    }

    /**
     * Get per-record memory threshold
     *
     * @param string $level
     * @return int|null
     */
    public function getPerRecordMemoryThreshold(string $level): ?int
    {
        return $this->settings['memory']['per_record'][$level] ?? null;
    }

    /**
     * Get all per-record memory thresholds
     *
     * @return array
     */
    public function getPerRecordMemoryThresholds(): array
    {
        return $this->settings['memory']['per_record'] ?? [];
    }

    /**
     * Get memory option
     *
     * @param string $option
     * @return mixed
     */
    public function getMemoryOption(string $option)
    {
        return $this->settings['memory']['options'][$option] ?? null;
    }

    /**
     * Get all memory options
     *
     * @return array
     */
    public function getMemoryOptions(): array
    {
        return $this->settings['memory']['options'] ?? [];
    }

    /**
     * Get all memory settings
     *
     * @return array
     */
    public function getMemorySettings(): array
    {
        return $this->settings['memory'] ?? [];
    }

    /**
     * Check if debug verbose mode is enabled
     *
     * @return bool
     */
    public function isDebugVerbose(): bool
    {
        return $this->settings['debug']['verbose'] ?? false;
    }

    /**
     * Check if logging to file is enabled
     *
     * @return bool
     */
    public function isLogToFileEnabled(): bool
    {
        return $this->settings['debug']['log_to_file'] ?? false;
    }

    /**
     * Get debug log file path
     *
     * @return string
     */
    public function getLogFilePath(): string
    {
        return $this->settings['debug']['log_file'] ?? storage_path('logs/wimd-seeding.log');
    }

    /**
     * Get all debug settings
     *
     * @return array
     */
    public function getDebugSettings(): array
    {
        return $this->settings['debug'] ?? [];
    }

    /**
     * Set debug option
     *
     * @param string $option
     * @param mixed $value
     * @return self
     */
    public function setDebugOption(string $option, $value): self
    {
        $this->settings['debug'][$option] = $value;
        return $this;
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
     * Get all settings
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        return $this->settings;
    }

    /**
     * Reload configuration from file
     *
     * @return self
     */
    public function reload(): self
    {
        $this->settings = config('wimd', []);
        return $this;
    }

    /**
     * Get a nested configuration value using dot notation
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a nested configuration value using dot notation
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set(string $key, $value): self
    {
        data_set($this->settings, $key, $value);
        return $this;
    }
}