<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\ContentSystem\DataLoader;

use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type NavigationLoaderConfigData from NavigationLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class NavigationLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'navigation';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $rootId = null;
        if (\array_key_exists('rootId', $data)) {
            if (!\is_string($data['rootId']) || $data['rootId'] === '') {
                throw CategoryException::invalidFieldValueType('rootId', 'non-empty string', \gettype($data['rootId']));
            }
            $rootId = $data['rootId'];
        }

        $depth = null;
        if (\array_key_exists('depth', $data)) {
            if (!\is_int($data['depth']) || $data['depth'] < 1) {
                throw CategoryException::invalidFieldValueType('depth', 'positive int', \gettype($data['depth']));
            }
            $depth = $data['depth'];
        }

        $activeProperty = 'activeId';
        if (\array_key_exists('activeProperty', $data)) {
            if (!\is_string($data['activeProperty']) || $data['activeProperty'] === '') {
                throw CategoryException::invalidFieldValueType('activeProperty', 'non-empty string', \gettype($data['activeProperty']));
            }
            $activeProperty = $data['activeProperty'];
        }

        return new NavigationLoaderConfig($rootId, $depth, $activeProperty);
    }

    /**
     * @return NavigationLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof NavigationLoaderConfig) {
            throw CategoryException::invalidFieldValueType('config', NavigationLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
