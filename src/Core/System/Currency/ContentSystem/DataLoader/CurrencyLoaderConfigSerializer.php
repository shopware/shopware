<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyException;

/**
 * @phpstan-import-type CurrencyLoaderConfigData from CurrencyLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class CurrencyLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'currency';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw CurrencyException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw CurrencyException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }
                $associations[] = $association;
            }
        }

        return new CurrencyLoaderConfig($associations);
    }

    /**
     * @return CurrencyLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof CurrencyLoaderConfig) {
            throw CurrencyException::invalidFieldValueType('config', CurrencyLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        return $data;
    }
}
