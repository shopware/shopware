<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class StorefrontContextLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return StorefrontContextLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if ($data !== []) {
            throw ContentSystemException::invalidFieldValueType('config', 'empty array', 'non-empty array');
        }

        return new StorefrontContextLoaderConfig();
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof StorefrontContextLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', StorefrontContextLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
