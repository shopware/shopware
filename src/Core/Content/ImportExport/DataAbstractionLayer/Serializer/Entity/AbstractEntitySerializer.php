<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\Struct;

abstract class AbstractEntitySerializer
{
    protected SerializerRegistry $serializerRegistry;

    /**
     * @param array|Struct|null $entity
     *
     * @return \Generator
     */
    abstract public function serialize(Config $config, EntityDefinition $definition, $entity): iterable;

    /**
     * @param array|\Traversable $entity
     *
     * @return array|\Traversable
     */
    abstract public function deserialize(Config $config, EntityDefinition $definition, $entity);

    abstract public function supports(string $entity): bool;

    public function setRegistry(SerializerRegistry $serializerRegistry): void
    {
        $this->serializerRegistry = $serializerRegistry;
    }

    protected function getDecorated(): AbstractEntitySerializer
    {
        throw new \RuntimeException('Implement getDecorated');
    }
}
