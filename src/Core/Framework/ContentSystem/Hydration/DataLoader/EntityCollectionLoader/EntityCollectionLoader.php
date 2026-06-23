<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypesResolvedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
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

    #[AsEventListener(event: ContentSystemDataLoaderTypesResolvedEvent::class . '.' . self::SOURCE)]
    public function onTypesResolved(ContentSystemDataLoaderTypesResolvedEvent $event): void
    {
        $types = [];
        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            $collectionClass = $definition->getCollectionClass();

            if ($collectionClass === EntityCollection::class) {
                continue;
            }

            /** @var class-string<Struct> $collectionClass */
            $types[] = new ContentSystemDataLoaderTypeDescriptor($collectionClass);
        }

        $event->types = $types;
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        if (!$config instanceof EntityLoaderConfig) {
            return ContentDataLoaderResult::notFound();
        }

        $propertyName = $config->property ?? $config->entity . 'Ids';
        $entityIds = $element->getProperty($propertyName);

        if ($entityIds === null) {
            return $this->emptyCollectionResult($config->entity);
        }

        if (!\is_array($entityIds)) {
            return ContentDataLoaderResult::notFound();
        }

        $entityIds = \array_filter($entityIds, static fn ($id) => \is_string($id));
        $entityIds = \array_map(static fn ($entityId) => u($entityId)->lower()->toString(), $entityIds);
        $entityIds = \array_values($entityIds);

        if ($entityIds === []) {
            return $this->emptyCollectionResult($config->entity);
        }

        $entities = $this->loadEntities($config->entity, $entityIds, $config->associations, $context);

        $definition = $this->definitionRegistry->getByEntityName($config->entity);
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
        $definition = $this->definitionRegistry->getByEntityName($entityName);
        /** @var class-string<EntityCollection<Entity>> $collectionClass */
        $collectionClass = $definition->getCollectionClass();

        return ContentDataLoaderResult::cached(new $collectionClass());
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
            if (\is_string($association)) {
                $criteria->addAssociation($association);
            }
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
