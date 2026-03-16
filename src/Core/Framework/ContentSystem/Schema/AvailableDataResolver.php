<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderTypeDescriptor;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class AvailableDataResolver
{
    /**
     * @param array<string, list<array{className: class-string<Struct>, genericParameters: list<class-string<Struct>>}>> $compiledSourceToTypes
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly array $compiledSourceToTypes,
    ) {
    }

    public function resolve(): AvailableDataMap
    {
        $sourceToTypes = [];

        foreach ($this->compiledSourceToTypes as $source => $entries) {
            $sourceToTypes[$source] ??= [];

            foreach ($entries as $entry) {
                if ($entry['className'] === Entity::class) {
                    array_push($sourceToTypes[$source], ...$this->expandAllEntities());

                    continue;
                }

                if ($entry['className'] === EntityCollection::class) {
                    array_push($sourceToTypes[$source], ...$this->expandAllEntityCollections());

                    continue;
                }

                $sourceToTypes[$source][] = new ContentDataLoaderTypeDescriptor(
                    $entry['className'],
                    $entry['genericParameters'],
                );
            }
        }

        return new AvailableDataMap($sourceToTypes);
    }

    /**
     * @return list<ContentDataLoaderTypeDescriptor>
     */
    private function expandAllEntities(): array
    {
        $types = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            $entityClass = $definition->getEntityClass();
            if ($entityClass === ArrayEntity::class) {
                continue;
            }

            $types[] = new ContentDataLoaderTypeDescriptor($entityClass);
        }

        return $types;
    }

    /**
     * @return list<ContentDataLoaderTypeDescriptor>
     */
    private function expandAllEntityCollections(): array
    {
        $types = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            $collectionClass = $definition->getCollectionClass();
            if ($collectionClass === EntityCollection::class) {
                continue;
            }

            /** @var class-string<Struct> $collectionClass */
            $types[] = new ContentDataLoaderTypeDescriptor($collectionClass);
        }

        return $types;
    }
}
