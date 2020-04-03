<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class EntityPipe extends AbstractPipe
{
    /**
     * @var SerializerRegistry
     */
    private $serializerRegistry;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var EntityDefinition|null
     */
    private $definition;

    /**
     * @var AbstractEntitySerializer|null
     */
    private $entitySerializer;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SerializerRegistry $serializerRegistry,
        ?EntityDefinition $definition = null,
        ?AbstractEntitySerializer $entitySerializer = null
    ) {
        $this->serializerRegistry = $serializerRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->definition = $definition;
        $this->entitySerializer = $entitySerializer;
    }

    public function in(Config $config, $entity): iterable
    {
        $this->loadConfig($config);

        return $this->entitySerializer->serialize($config, $this->definition, $entity);
    }

    public function out(Config $config, iterable $encoded): iterable
    {
        $this->loadConfig($config);

        return $this->entitySerializer->deserialize($config, $this->definition, $encoded);
    }

    private function loadConfig(Config $config): void
    {
        $this->definition = $this->definition
            ?? $this->definitionInstanceRegistry->getByEntityName($config->get('sourceEntity') ?? '');

        $this->entitySerializer = $this->entitySerializer ?? $this->serializerRegistry->getEntity($this->definition->getEntityName());
    }
}
