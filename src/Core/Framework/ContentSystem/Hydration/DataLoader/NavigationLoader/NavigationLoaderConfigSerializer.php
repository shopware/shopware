<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\NavigationLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-import-type NavigationLoaderConfigData from NavigationLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class NavigationLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function getDecorated(): AbstractContentDataLoaderConfigSerializer
    {
        throw new DecorationPatternException(self::class);
    }

    public static function getSource(): string
    {
        return 'navigation';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $rootId = null;
        if (\array_key_exists('rootId', $data)) {
            if (!\is_string($data['rootId']) || $data['rootId'] === '') {
                throw ContentSystemException::invalidFieldValueType('rootId', 'non-empty string', \gettype($data['rootId']));
            }
            $rootId = $data['rootId'];
        }

        $depth = NavigationLoaderConfig::DEFAULT_DEPTH;
        if (\array_key_exists('depth', $data)) {
            if (!\is_int($data['depth']) || $data['depth'] < 1) {
                throw ContentSystemException::invalidFieldValueType('depth', 'positive int', \gettype($data['depth']));
            }
            $depth = $data['depth'];
        }

        $activeProperty = 'activeId';
        if (\array_key_exists('activeProperty', $data)) {
            if (!\is_string($data['activeProperty']) || $data['activeProperty'] === '') {
                throw ContentSystemException::invalidFieldValueType('activeProperty', 'non-empty string', \gettype($data['activeProperty']));
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
            throw ContentSystemException::invalidFieldValueType('config', NavigationLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->rootId !== null) {
            $data['rootId'] = $config->rootId;
        }

        if ($config->depth !== NavigationLoaderConfig::DEFAULT_DEPTH) {
            $data['depth'] = $config->depth;
        }

        if ($config->activeProperty !== 'activeId') {
            $data['activeProperty'] = $config->activeProperty;
        }

        return $data;
    }
}
