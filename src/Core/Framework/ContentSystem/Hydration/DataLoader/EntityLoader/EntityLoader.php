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
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
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
            return ContentDataLoaderResult::notFound();
        }

        $entityId = $inputs->stringOrNull('property');

        if ($entityId === null) {
            return ContentDataLoaderResult::notFound();
        }

        $entityId = u($entityId)->lower()->toString();

        // A PropertyReference value passes LoaderInputResolver::dereference()'s string type check untouched, so
        // an unsubstituted template placeholder (e.g. "{{productId}}" left literal on a layout not rooted on
        // that entity) reaches here as-is. Anything but an id therefore degrades rather than reaching
        // Uuid::fromHexToBytes() in EntityDefinitionQueryHelper::addIdCondition()
        // (src/Core/Framework/DataAbstractionLayer/Dbal/EntityDefinitionQueryHelper.php:612), where the
        // criteria id below is converted. The guard runs after the lowercase because Uuid::VALID_PATTERN is
        // lowercase-only and would reject an uppercase id.
        if (!Uuid::isValid($entityId)) {
            return ContentDataLoaderResult::notFound();
        }

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. Catch the
        // covering ancestor rather than an enumerated set: the reachable set is open, and this loader searches
        // an arbitrary registered entity, so the repositories and their decorators it can reach are not
        // enumerable from here at all.
        try {
            $entity = $this->loadEntity($entityName, $entityId, $inputs->stringList('associations'), $context);

            if ($entity === null) {
                return ContentDataLoaderResult::notFound();
            }

            // The has() check above only proves the entity name is in the registry's map
            // (DefinitionInstanceRegistry::has() is an isset on it); getByEntityName() still throws
            // DefinitionNotFoundException when the mapped definition service is absent from the container, so
            // it sits inside the catch rather than after it.
            $definition = $this->definitionRegistry->getByEntityName($entityName);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        $cacheTag = $this->cacheTagResolver->resolve($definition, $entityId);

        if ($cacheTag === null) {
            return ContentDataLoaderResult::uncacheable($entity);
        }

        return ContentDataLoaderResult::cached($entity, $cacheTag);
    }

    /**
     * @param list<string> $associations
     */
    private function loadEntity(
        string $entityName,
        string $entityId,
        array $associations,
        SalesChannelContext $context
    ): SalesChannelEntity|Entity|null {
        $criteria = new Criteria([$entityId]);

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

        return $result->getEntities()->first();
    }
}
