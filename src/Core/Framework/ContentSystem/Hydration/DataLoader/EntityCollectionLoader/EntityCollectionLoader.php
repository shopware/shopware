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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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
            return ContentDataLoaderResult::noInput();
        }

        $entityIds = $inputs->stringListOrNull('property');

        if ($entityIds === null || $entityIds === []) {
            return $this->emptyCollectionResult($entityName);
        }

        $entityIds = \array_map(static fn (string $entityId) => u($entityId)->lower()->toString(), $entityIds);

        // An unsubstituted placeholder left literal in the stored list passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() in EntityDefinitionQueryHelper::addIdCondition(). One bad entry degrades the
        // whole element rather than being filtered out: a malformed string means broken authoring, and a
        // silently shortened collection would hide it.
        foreach ($entityIds as $entityId) {
            if (!Uuid::isValid($entityId)) {
                return ContentDataLoaderResult::noInput();
            }
        }

        // Any ShopwareHttpException degrades the element to noInput() before the search and, during it, to
        // foundNothing() over the tags of the requested ids; when an id's entity has no tag pattern, no tag
        // covers the result and that outcome is uncacheable(). Everything else, such as a \TypeError or a
        // database driver failure, propagates. Why the catch is the covering ancestor and never an enumerated
        // union:
        // src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // The set is fully open here: this loader searches an arbitrary registered entity.
        // The has() check above only proves the entity name is in the registry's map
        // (DefinitionInstanceRegistry::has() is an isset on it); getByEntityName() still throws
        // DefinitionNotFoundException when the mapped definition service is absent from the container, so it
        // sits inside a catch. No lookup ran yet, so the result records no tag.
        try {
            $definition = $this->definitionRegistry->getByEntityName($entityName);
            $repository = $this->resolveRepository($entityName);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::noInput();
        }

        $requestedTags = $this->requestedTags($definition, $entityIds);

        try {
            $entities = $this->search($repository, $entityIds, $inputs->stringList('associations'), $context);
        } catch (ShopwareHttpException) {
            if ($requestedTags === null) {
                return ContentDataLoaderResult::uncacheable();
            }

            return ContentDataLoaderResult::foundNothing(...$requestedTags);
        }

        if ($requestedTags === null) {
            return ContentDataLoaderResult::uncacheable($entities);
        }

        $tags = $requestedTags;

        foreach ($entities as $entity) {
            $tag = $this->cacheTagResolver->resolve($definition, $entity->getUniqueIdentifier());

            if ($tag === null) {
                return ContentDataLoaderResult::uncacheable($entities);
            }

            $tags[] = $tag;
        }

        return ContentDataLoaderResult::cached($entities, ...array_values(array_unique($tags)));
    }

    /**
     * The tag of every requested id, so that an id which returns no entity is covered too. Null when the entity
     * has no tag pattern, because then no tag covers the result.
     *
     * @param non-empty-list<string> $entityIds
     *
     * @return non-empty-list<string>|null
     */
    private function requestedTags(EntityDefinition $definition, array $entityIds): ?array
    {
        $tags = [];

        foreach ($entityIds as $entityId) {
            $tag = $this->cacheTagResolver->resolve($definition, $entityId);

            if ($tag === null) {
                return null;
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Degrades rather than propagating: resolveDefinition() throws DefinitionNotFoundException when the
     * mapped definition service is absent from the container. resolveDefinition() itself stays throwing,
     * because resolveProducedType() is an introspection path that must fail hard on an unknown entity.
     * No id was requested, so no lookup runs and the result records no tag.
     */
    private function emptyCollectionResult(string $entityName): ContentDataLoaderResult
    {
        try {
            /** @var class-string<EntityCollection<Entity>> $collectionClass */
            $collectionClass = $this->resolveDefinition($entityName)->getCollectionClass();
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::noInput();
        }

        return ContentDataLoaderResult::noInput(new $collectionClass());
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
     * @return SalesChannelRepository<covariant EntityCollection<covariant Entity>>|EntityRepository<covariant EntityCollection<covariant Entity>>
     */
    private function resolveRepository(string $entityName): SalesChannelRepository|EntityRepository
    {
        try {
            return $this->salesChannelDefinitionRegistry->getSalesChannelRepository($entityName);
        } catch (SalesChannelRepositoryNotFoundException) {
            return $this->definitionRegistry->getRepository($entityName);
        }
    }

    /**
     * @param SalesChannelRepository<covariant EntityCollection<covariant Entity>>|EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     * @param list<string> $entityIds
     * @param list<string> $associations
     *
     * @return EntityCollection<covariant Entity>
     */
    private function search(
        SalesChannelRepository|EntityRepository $repository,
        array $entityIds,
        array $associations,
        SalesChannelContext $context
    ): EntityCollection {
        $criteria = new Criteria($entityIds);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        if ($repository instanceof SalesChannelRepository) {
            return $repository->search($criteria, $context)->getEntities();
        }

        return $repository->search($criteria, $context->getContext())->getEntities();
    }
}
