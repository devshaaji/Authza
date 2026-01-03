<?php

declare(strict_types=1);

namespace Authza\Console;

/**
 * Manages the remembered config path for CLI convenience
 * 
 * Stores the last used config path in .authza file in the working directory
 * so users don't need to specify --config on every command after init.
 */
class ConfigPathResolver
{
    private const REMEMBERED_CONFIG_FILE = '.authza';

    /**
     * Get the path to the remembered config file
     */
    public static function getRememberedConfigFile(?string $workingDir = null): string
    {
        $baseDir = $workingDir ?? getcwd();
        return $baseDir . DIRECTORY_SEPARATOR . self::REMEMBERED_CONFIG_FILE;
    }

    /**
     * Save the config path to be remembered for future CLI calls
     */
    public static function rememberConfigPath(string $configPath, ?string $workingDir = null): bool
    {
        $rememberedFile = self::getRememberedConfigFile($workingDir);
        return file_put_contents($rememberedFile, $configPath) !== false;
    }

    /**
     * Get the remembered config path if it exists and is valid
     */
    public static function getRememberedConfigPath(?string $workingDir = null): ?string
    {
        $rememberedFile = self::getRememberedConfigFile($workingDir);
        
        if (!file_exists($rememberedFile)) {
            return null;
        }

        $configPath = trim(file_get_contents($rememberedFile));
        
        if ($configPath === '' || !file_exists($configPath)) {
            return null;
        }

        return $configPath;
    }

    /**
     * Check if a config path is already remembered
     */
    public static function hasRememberedConfig(?string $workingDir = null): bool
    {
        return self::getRememberedConfigPath($workingDir) !== null;
    }

    /**
     * Clear the remembered config path
     */
    public static function forgetConfigPath(?string $workingDir = null): bool
    {
        $rememberedFile = self::getRememberedConfigFile($workingDir);
        
        if (file_exists($rememberedFile)) {
            return unlink($rememberedFile);
        }

        return true;
    }
}
