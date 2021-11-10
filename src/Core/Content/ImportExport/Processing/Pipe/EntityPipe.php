<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Struct\Struct;

class EntityPipe extends AbstractPipe
{
    private SerializerRegistry $serializerRegistry;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private ?EntityDefinition $definition;

    private ?AbstractEntitySerializer $entitySerializer;

    private ?PrimaryKeyResolver $primaryKeyResolver;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SerializerRegistry $serializerRegistry,
        ?EntityDefinition $definition = null,
        ?AbstractEntitySerializer $entitySerializer = null,
        ?PrimaryKeyResolver $primaryKeyResolver = null
    ) {
        $this->serializerRegistry = $serializerRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->definition = $definition;
        $this->entitySerializer = $entitySerializer;
        $this->primaryKeyResolver = $primaryKeyResolver;
    }

    /**
     * @param iterable|Struct $record
     */
    public function in(Config $config, $record): iterable
    {
        $this->loadConfig($config);

        return $this->entitySerializer->serialize($config, $this->definition, $record);
    }

    public function out(Config $config, iterable $record): iterable
    {
        $this->loadConfig($config);

        if ($this->primaryKeyResolver) {
            $record = $this->primaryKeyResolver->resolvePrimaryKeyFromUpdatedBy($config, $this->definition, $record);
        }

        return $this->entitySerializer->deserialize($config, $this->definition, $record);
    }

    private function loadConfig(Config $config): void
    {
        $this->definition = $this->definition
            ?? $this->definitionInstanceRegistry->getByEntityName($config->get('sourceEntity') ?? '');

        $this->entitySerializer = $this->entitySerializer ?? $this->serializerRegistry->getEntity($this->definition->getEntityName());
    }
}
