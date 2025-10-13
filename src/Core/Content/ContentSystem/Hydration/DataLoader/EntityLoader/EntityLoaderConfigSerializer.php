<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigInterface;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigSerializerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type EntityLoaderConfigData from EntityLoaderConfig
 *
 * @internal
 */
#[Package('discovery')]
class EntityLoaderConfigSerializer implements ContentDataLoaderConfigSerializerInterface
{
    public static function getSource(): string
    {
        return 'entity';
    }

    public function decode(array $data): ContentDataLoaderConfigInterface
    {
        if (!isset($data['entity']) || !\is_string($data['entity']) || $data['entity'] === '') {
            throw ContentSystemException::invalidFieldValueType('entity', 'non-empty string', \gettype($data['entity'] ?? null));
        }
        $entity = $data['entity'];

        if (!isset($data['property']) || !\is_string($data['property']) || $data['property'] === '') {
            throw ContentSystemException::invalidFieldValueType('property', 'non-empty string', \gettype($data['property']));
        }
        $property = $data['property'];

        $associations = [];
        if (isset($data['associations'])) {
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
    public function encode(ContentDataLoaderConfigInterface $config): array
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
