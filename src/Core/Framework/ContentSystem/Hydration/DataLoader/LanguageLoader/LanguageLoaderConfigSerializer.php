<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LanguageLoader;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-import-type LanguageLoaderConfigData from LanguageLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LanguageLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function getDecorated(): AbstractContentDataLoaderConfigSerializer
    {
        throw new DecorationPatternException(self::class);
    }

    public static function getSource(): string
    {
        return 'language';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw ContentSystemException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw ContentSystemException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }
                $associations[] = $association;
            }
        }

        return new LanguageLoaderConfig($associations);
    }

    /**
     * @return LanguageLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof LanguageLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', LanguageLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        return $data;
    }
}
