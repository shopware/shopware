<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderTypeResolver
{
    /**
     * @param array<string, list<array{className: class-string<Struct>, genericParameters: list<class-string<Struct>>}>> $compiledSourceToTypes
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly array $compiledSourceToTypes,
    ) {
    }

    public function resolve(): ContentSystemDataLoaderTypeMap
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

                $sourceToTypes[$source][] = new ContentSystemDataLoaderTypeDescriptor(
                    $entry['className'],
                    $entry['genericParameters'],
                );
            }
        }

        return new ContentSystemDataLoaderTypeMap($sourceToTypes);
    }

    /**
     * @return list<ContentSystemDataLoaderTypeDescriptor>
     */
    private function expandAllEntities(): array
    {
        $types = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            $entityClass = $definition->getEntityClass();
            // ArrayEntity is the fallback for definitions without a custom entity class;
            // it's excluded from wildcard expansion since it's not a domain type.
            // Loaders explicitly declaring ArrayEntity via @extends are unaffected.
            if ($entityClass === ArrayEntity::class) {
                continue;
            }

            $types[] = new ContentSystemDataLoaderTypeDescriptor($entityClass);
        }

        return $types;
    }

    /**
     * @return list<ContentSystemDataLoaderTypeDescriptor>
     */
    private function expandAllEntityCollections(): array
    {
        $types = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            $collectionClass = $definition->getCollectionClass();
            // EntityCollection is the fallback for definitions without a custom collection class;
            // excluded from wildcard expansion for the same reason as ArrayEntity above.
            if ($collectionClass === EntityCollection::class) {
                continue;
            }

            /** @var class-string<Struct> $collectionClass */
            $types[] = new ContentSystemDataLoaderTypeDescriptor($collectionClass);
        }

        return $types;
    }
}
