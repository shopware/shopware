<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<Entity>
 */
#[Package('framework')]
class EntityLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'entity';

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

            if ($definition->getEntityClass() === ArrayEntity::class) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $producedType = isset($salesChannelDefinitions[$entityName])
                ? $salesChannelDefinitions[$entityName]->getEntityClass()
                : $definition->getEntityClass();

            $capabilities[] = new LoaderTypeCapability($producedType, ['entity' => $entityName]);
        }

        return $capabilities;
    }

    public function resolveProducedType(AbstractContentDataLoaderConfig $config): string
    {
        if (!$config instanceof EntityLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, $config::class);
        }

        if ($this->salesChannelDefinitionRegistry->has($config->entity)) {
            return $this->salesChannelDefinitionRegistry->getByEntityName($config->entity)->getEntityClass();
        }

        try {
            return $this->definitionRegistry->getByEntityName($config->entity)->getEntityClass();
        } catch (DefinitionNotFoundException) {
            throw ContentSystemException::unknownLoaderEntity($config->entity);
        }
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
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

        $entityId = $inputs->stringOrNull('property');

        if ($entityId === null) {
            return ContentDataLoaderResult::noInput();
        }

        $entityId = u($entityId)->lower()->toString();

        // An unsubstituted placeholder such as "{{productId}}" passes LoaderInputResolver::dereference()
        // untouched; guard after the lowercase (Uuid::VALID_PATTERN is lowercase-only) instead of reaching
        // Uuid::fromHexToBytes() in EntityDefinitionQueryHelper::addIdCondition().
        if (!Uuid::isValid($entityId)) {
            return ContentDataLoaderResult::noInput();
        }

        // Any ShopwareHttpException degrades the element to noInput() before the search and, during it, to
        // foundNothing() tagged with the requested id; an entity without a tag pattern has no tag to name, so
        // that outcome is uncacheable(). Everything else, such as a \TypeError or a database driver failure,
        // propagates. Why the catch is the covering ancestor and never an enumerated union:
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

        $cacheTag = $this->cacheTagResolver->resolve($definition, $entityId);

        try {
            $entity = $this->search($repository, $entityId, $inputs->stringList('associations'), $context);
        } catch (ShopwareHttpException) {
            return $this->foundNothing($cacheTag);
        }

        if ($entity === null) {
            return $this->foundNothing($cacheTag);
        }

        if ($cacheTag === null) {
            return ContentDataLoaderResult::uncacheable($entity);
        }

        return ContentDataLoaderResult::cached($entity, $cacheTag);
    }

    /**
     * The tag of the requested id covers the empty result. Without a tag pattern for the entity, no tag covers
     * it and the render is not cached.
     */
    private function foundNothing(?string $cacheTag): ContentDataLoaderResult
    {
        if ($cacheTag === null) {
            return ContentDataLoaderResult::uncacheable();
        }

        return ContentDataLoaderResult::foundNothing($cacheTag);
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
     * @param list<string> $associations
     */
    private function search(
        SalesChannelRepository|EntityRepository $repository,
        string $entityId,
        array $associations,
        SalesChannelContext $context
    ): SalesChannelEntity|Entity|null {
        $criteria = new Criteria([$entityId]);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        if ($repository instanceof SalesChannelRepository) {
            return $repository->search($criteria, $context)->getEntities()->first();
        }

        return $repository->search($criteria, $context->getContext())->getEntities()->first();
    }
}
