<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type BreadcrumbLoaderConfigData from BreadcrumbLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class BreadcrumbLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'breadcrumb';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $property = null;
        if (\array_key_exists('property', $data)) {
            if (!\is_string($data['property']) || $data['property'] === '') {
                throw BreadcrumbException::invalidFieldValueType('property', 'non-empty string', \gettype($data['property']));
            }
            $property = $data['property'];
        }

        $type = 'product';
        if (\array_key_exists('type', $data)) {
            if (!\is_string($data['type']) || $data['type'] === '') {
                throw BreadcrumbException::invalidFieldValueType('type', 'non-empty string', \gettype($data['type']));
            }
            $type = $data['type'];
        }

        $referrerCategoryProperty = null;
        if (\array_key_exists('referrerCategoryProperty', $data)) {
            if (!\is_string($data['referrerCategoryProperty']) || $data['referrerCategoryProperty'] === '') {
                throw BreadcrumbException::invalidFieldValueType('referrerCategoryProperty', 'non-empty string', \gettype($data['referrerCategoryProperty']));
            }
            $referrerCategoryProperty = $data['referrerCategoryProperty'];
        }

        return new BreadcrumbLoaderConfig($property, $type, $referrerCategoryProperty);
    }

    /**
     * @return BreadcrumbLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof BreadcrumbLoaderConfig) {
            throw BreadcrumbException::invalidFieldValueType('config', BreadcrumbLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
