<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;

class DocumentConfigurationFactory
{
    private function __construct()
    {
        //Factory is Static
    }

    public static function createConfiguration(array $specificConfig, ?DocumentBaseConfigEntity ...$configs): DocumentConfiguration
    {
        $configs = array_filter($configs);
        $documentConfiguration = new DocumentConfiguration();
        foreach ($configs as $config) {
            $documentConfiguration = static::mergeConfiguration($documentConfiguration, $config);
        }
        $documentConfiguration = static::mergeConfiguration($documentConfiguration, $specificConfig);

        return $documentConfiguration;
    }

    /**
     * @param DocumentBaseConfigEntity|DocumentConfiguration|array $additionalConfig
     */
    public static function mergeConfiguration(DocumentConfiguration $baseConfig, $additionalConfig): DocumentConfiguration
    {
        $additionalConfigArray = [];
        if (\is_array($additionalConfig)) {
            $additionalConfigArray = $additionalConfig;
        } elseif (\is_object($additionalConfig)) {
            $additionalConfigArray = $additionalConfig->jsonSerialize();
        }

        $additionalConfigArray = self::cleanConfig($additionalConfigArray);

        foreach ($additionalConfigArray as $key => $value) {
            if ($value !== null) {
                if ($key === 'custom' && \is_array($value)) {
                    $baseConfig->custom = array_merge($baseConfig->custom ?? [], $value);
                } elseif (strncmp($key, 'custom.', 7) === 0) {
                    $customKey = mb_substr($key, 7);
                    $baseConfig->custom = array_merge($baseConfig->custom ?? [], [$customKey => $value]);
                } else {
                    $baseConfig->$key = $value;
                }
            }
        }

        return $baseConfig;
    }

    private static function cleanConfig(array $config): array
    {
        if (isset($config['config'])) {
            $config = array_merge($config, $config['config']);
            unset($config['config']);
        }

        $deleteKeys = [
            'viewData' => 1,
            '_uniqueIdentifier' => 1,
            'createdAt' => 1,
        ];

        return array_diff_key($config, $deleteKeys);
    }
}
