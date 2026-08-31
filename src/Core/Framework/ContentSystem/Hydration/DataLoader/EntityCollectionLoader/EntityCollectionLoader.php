<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<EntityCollection<Entity>>
 */
#[Package('framework')]
class EntityCollectionLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'entity_collection';

    public function __construct(
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityCacheTagResolver $cacheTagResolver,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function producibleTypes(): array
    {
        $salesChannelDefinitions = $this->salesChannelDefinitionRegistry->getSalesChannelDefinitions();

        $capabilities = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if ($definition instanceof MappingEntityDefinition) {
                continue;
            }

            if ($definition->getCollectionClass() === EntityCollection::class) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $producedDefinition = $salesChannelDefinitions[$entityName] ?? $definition;

            /** @var class-string<EntityCollection<Entity>> $collectionClass */
            $collectionClass = $producedDefinition->getCollectionClass();

            $capabilities[] = new LoaderTypeCapability(
                $collectionClass,
                ['entity' => $entityName],
                [$producedDefinition->getEntityClass()],
            );
        }

        return $capabilities;
    }

    public function resolveProducedType(AbstractContentDataLoaderConfig $config): string
    {
        if (!$config instanceof EntityLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, $config::class);
        }

        /** @var class-string<EntityCollection<Entity>> $collectionClass */
        $collectionClass = $this->resolveDefinition($config->entity)->getCollectionClass();

        return $collectionClass;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true, referencedType: 'list<string>'),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $entityName = $inputs->string('entity');

        if (!$this->definitionRegistry->has($entityName)) {
            return ContentDataLoaderResult::notFound();
        }

        $entityIds = $inputs->stringListOrNull('property');

        if ($entityIds === null || $entityIds === []) {
            return $this->emptyCollectionResult($entityName);
        }

        $entityIds = \array_map(static fn (string $entityId) => u($entityId)->lower()->toString(), $entityIds);

        $entities = $this->loadEntities($entityName, $entityIds, $inputs->stringList('associations'), $context);

        $definition = $this->definitionRegistry->getByEntityName($entityName);
        $tags = [];

        foreach ($entities as $entity) {
            $tag = $this->cacheTagResolver->resolve($definition, $entity->getUniqueIdentifier());

            if ($tag === null) {
                return ContentDataLoaderResult::uncacheable($entities);
            }

            $tags[] = $tag;
        }

        return ContentDataLoaderResult::cached($entities, ...$tags);
    }

    private function emptyCollectionResult(string $entityName): ContentDataLoaderResult
    {
        /** @var class-string<EntityCollection<Entity>> $collectionClass */
        $collectionClass = $this->resolveDefinition($entityName)->getCollectionClass();

        return ContentDataLoaderResult::cached(new $collectionClass());
    }

    /**
     * The sales-channel definition for the entity where one exists, otherwise the base definition.
     */
    private function resolveDefinition(string $entityName): EntityDefinition
    {
        if ($this->salesChannelDefinitionRegistry->has($entityName)) {
            return $this->salesChannelDefinitionRegistry->getByEntityName($entityName);
        }

        try {
            return $this->definitionRegistry->getByEntityName($entityName);
        } catch (DefinitionNotFoundException) {
            throw ContentSystemException::unknownLoaderEntity($entityName);
        }
    }

    /**
     * @param list<string> $entityIds
     * @param list<string> $associations
     *
     * @return EntityCollection<covariant Entity>
     */
    private function loadEntities(
        string $entityName,
        array $entityIds,
        array $associations,
        SalesChannelContext $context
    ): EntityCollection {
        $criteria = new Criteria($entityIds);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        try {
            $salesChannelRepository = $this->salesChannelDefinitionRegistry->getSalesChannelRepository($entityName);
            $result = $salesChannelRepository->search($criteria, $context);
        } catch (SalesChannelRepositoryNotFoundException) {
            $repository = $this->definitionRegistry->getRepository($entityName);
            $result = $repository->search($criteria, $context->getContext());
        }

        return $result->getEntities();
    }
}
