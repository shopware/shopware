<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

#[Package('after-sales')]
class DocumentConfigurationFactory
{
    private function __construct()
    {
        // Factory is Static
    }

    /**
     * @param array<string, bool|int|string|array<array-key, mixed>|null> $specificConfig
     */
    public static function createConfiguration(array $specificConfig, ?DocumentBaseConfigEntity ...$configs): DocumentConfiguration
    {
        $configs = array_filter($configs);
        $documentConfiguration = new DocumentConfiguration();
        foreach ($configs as $config) {
            $documentConfiguration = static::mergeConfiguration($documentConfiguration, $config);
        }

        return static::mergeConfiguration($documentConfiguration, $specificConfig);
    }

    /**
     * @param DocumentBaseConfigEntity|DocumentConfiguration|array<string, bool|int|string|array<array-key, mixed>|null> $additionalConfig
     */
    public static function mergeConfiguration(DocumentConfiguration $baseConfig, DocumentBaseConfigEntity|DocumentConfiguration|array $additionalConfig): DocumentConfiguration
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
                    $baseConfig->__set('custom', array_merge((array) $baseConfig->__get('custom'), $value));
                } elseif (str_starts_with($key, 'custom.')) {
                    $customKey = mb_substr($key, 7);
                    $baseConfig->__set('custom', array_merge((array) $baseConfig->__get('custom'), [$customKey => $value]));
                } elseif ($key === 'companyCountry' && \is_array($value)) {
                    $baseConfig->setCompanyCountry((new CountryEntity())->assign($value));
                } else {
                    $baseConfig->__set($key, $value);
                }
            }
        }

        return $baseConfig;
    }

    /**
     * @param array<bool|int|string|array<array-key, mixed>|null> $config
     *
     * @return array<bool|int|string|array<array-key, mixed>|null>
     */
    private static function cleanConfig(array $config): array
    {
        if (isset($config['config']) && \is_array($config['config'])) {
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
