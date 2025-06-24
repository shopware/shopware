<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

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
     * @param DocumentBaseConfigEntity|DocumentConfiguration|array<string, mixed> $additionalConfig
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
                    continue;
                }

                if (str_starts_with($key, 'custom.')) {
                    $customKey = mb_substr($key, 7);
                    $baseConfig->__set('custom', array_merge((array) $baseConfig->__get('custom'), [$customKey => $value]));
                    continue;
                }

                if (!property_exists($baseConfig, $key)) {
                    $baseConfig->__set($key, $value);
                    continue;
                }

                $property = new \ReflectionProperty($baseConfig, $key);
                $propertyType = $property->getType();

                if (!($propertyType instanceof \ReflectionNamedType)) {
                    $baseConfig->__set($key, $value);
                    continue;
                }

                $typeName = $propertyType->getName();
                $setterMethod = 'set' . ucfirst($key);

                /**
                 * Using dynamic access to handle entity properties generically, which improves maintainability by
                 * automatically supporting new entity properties without code changes with a static
                 * switch/if-else approach.
                 */
                if (method_exists($baseConfig, $setterMethod)) {
                    if (is_subclass_of($typeName, Struct::class) && \is_array($value)) {
                        // @phpstan-ignore symplify.noDynamicName
                        $baseConfig->$setterMethod((new $typeName())->assign($value));
                        continue;
                    }

                    // @phpstan-ignore symplify.noDynamicName
                    $baseConfig->$setterMethod($value);
                    continue;
                }

                if (!is_subclass_of($typeName, Struct::class) || !\is_array($value)) {
                    $baseConfig->__set($key, $value);
                    continue;
                }

                // @phpstan-ignore symplify.noDynamicName
                $baseConfig->{$key} = (new $typeName())->assign($value);
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
