<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-import-type EntityLoaderConfigData from EntityLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class EntityLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function getDecorated(): AbstractContentDataLoaderConfigSerializer
    {
        throw new DecorationPatternException(self::class);
    }

    public static function getSource(): string
    {
        return EntityLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (!\array_key_exists('entity', $data) || !\is_string($data['entity']) || $data['entity'] === '') {
            throw ContentSystemException::invalidFieldValueType('entity', 'non-empty string', \gettype($data['entity'] ?? null));
        }
        $entity = $data['entity'];

        if (!\array_key_exists('property', $data) || !\is_string($data['property']) || $data['property'] === '') {
            throw ContentSystemException::invalidFieldValueType('property', 'non-empty string', \gettype($data['property'] ?? null));
        }
        $property = $data['property'];

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

        return new EntityLoaderConfig($entity, $property, $associations);
    }

    /**
     * @return EntityLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof EntityLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, $config::class);
        }

        $data = [
            'entity' => $config->entity,
            'property' => $config->property,
        ];

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        return $data;
    }
}
