<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataEnrichExtension;
use Shopware\Core\Content\Cms\Extension\CmsSlotsDataResolveExtension;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('buyers-experience')]
class CmsSlotsDataResolver
{
    /**
     * @var array<string, CmsElementResolverInterface>
     */
    private array $resolvers = [];

    /**
     * @internal
     *
     * @param iterable<CmsElementResolverInterface> $resolvers
     * @param array<string, SalesChannelRepository> $repositories
     */
    public function __construct(
        iterable $resolvers,
        private readonly array $repositories,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly ExtensionDispatcher $extensions,
    ) {
        foreach ($resolvers as $resolver) {
            $this->resolvers[$resolver->getType()] = $resolver;
        }
    }

    private function __resolve(CmsSlotCollection $slots, ResolverContext $resolverContext): CmsSlotCollection
    {
        $criteriaList = $this->extensions->publish(
            name: CmsSlotsDataCollectExtension::NAME,
            extension: new CmsSlotsDataCollectExtension($slots, $resolverContext),
            function: $this->collectCriteriaList(...)
        );

        // reduce search requests by combining mergeable criteria objects
        [$directReads, $searches] = $this->optimizeCriteriaObjects($criteriaList);

        // fetch data from storage
        $identifierResult = $this->fetchByIdentifier($directReads, $resolverContext->getSalesChannelContext());
        $criteriaResult = $this->fetchByCriteria($searches, $resolverContext->getSalesChannelContext());

        return $this->extensions->publish(
            name: CmsSlotsDataEnrichExtension::NAME,
            extension: new CmsSlotsDataEnrichExtension(
                slots: $slots,
                criteriaList: $criteriaList,
                identifierResult: $identifierResult,
                criteriaResult: $criteriaResult,
                resolverContext: $resolverContext,
            ),
            function: $this->enrichCmsSlots(...),
        );
    }

    public function resolve(CmsSlotCollection $slots, ResolverContext $resolverContext): CmsSlotCollection
    {
        return $this->extensions->publish(
            name: CmsSlotsDataResolveExtension::NAME,
            extension: new CmsSlotsDataResolveExtension($slots, $resolverContext),
            function: $this->__resolve(...),
        );
    }

    /**
     * @return array<string, CriteriaCollection>
     */
    private function collectCriteriaList(CmsSlotCollection $slots, ResolverContext $resolverContext): array
    {
        $result = [];
        foreach ($slots as $slot) {
            $resolver = $this->resolvers[$slot->getType()] ?? null;
            if (!$resolver) {
                continue;
            }

            $collection = $resolver->collect($slot, $resolverContext);
            if ($collection === null) {
                continue;
            }

            $result[$slot->getUniqueIdentifier()] = $collection;
        }

        return $result;
    }

    /**
     * @template TEntityCollection of EntityCollection
     *
     * @param array<CriteriaCollection> $criteriaList
     * @param array<EntitySearchResult<TEntityCollection>> $identifierResult
     * @param array<EntitySearchResult<TEntityCollection>> $criteriaResult
     */
    private function enrichCmsSlots(
        CmsSlotCollection $slots,
        array $criteriaList,
        array $identifierResult,
        array $criteriaResult,
        ResolverContext $resolverContext
    ): CmsSlotCollection {
        // create result for each slot with the requested data
        foreach ($slots as $slotId => $slot) {
            $resolver = $this->resolvers[$slot->getType()] ?? null;
            if (!$resolver) {
                continue;
            }

            $result = new ElementDataCollection();

            $this->mapSearchResults($result, $slot, $criteriaList, $criteriaResult);
            $this->mapEntities($result, $slot, $criteriaList, $identifierResult);

            $resolver->enrich($slot, $resolverContext, $result);

            // replace with return value from enrich(), because it's allowed to change the entity type
            $slots->set($slotId, $slot);
        }

        return $slots;
    }

    /**
     * @param string[][] $directReads
     *
     * @return array<string, EntitySearchResult<EntityCollection>>
     */
    private function fetchByIdentifier(array $directReads, SalesChannelContext $context): array
    {
        $entities = [];

        foreach ($directReads as $definitionClass => $ids) {
            $definition = $this->definitionRegistry->get($definitionClass);

            $repository = $this->getSalesChannelApiRepository($definition);

            if ($repository) {
                $entities[$definitionClass] = $repository->search(new Criteria($ids), $context);
            } else {
                $repository = $this->getApiRepository($definition);
                $entities[$definitionClass] = $repository->search(new Criteria($ids), $context->getContext());
            }
        }

        return $entities;
    }

    /**
     * @param array<string, array<string, Criteria>> $searches
     *
     * @return array<string, EntitySearchResult<EntityCollection>>
     */
    private function fetchByCriteria(array $searches, SalesChannelContext $context): array
    {
        $searchResults = [];

        foreach ($searches as $definitionClass => $criteriaObjects) {
            foreach ($criteriaObjects as $criteriaHash => $criteria) {
                $definition = $this->definitionRegistry->get($definitionClass);

                $repository = $this->getSalesChannelApiRepository($definition);

                if ($repository) {
                    $result = $repository->search($criteria, $context);
                } else {
                    $repository = $this->getApiRepository($definition);
                    $result = $repository->search($criteria, $context->getContext());
                }

                $searchResults[$criteriaHash] = $result;
            }
        }

        return $searchResults;
    }

    /**
     * @param array<string, CriteriaCollection> $criteriaCollections
     *
     * @return array{0: array<string, array<string>>, 1: array<string, array<string, Criteria>>}
     */
    private function optimizeCriteriaObjects(array $criteriaCollections): array
    {
        $directReads = [];
        $searches = [];

        $criteriaCollection = $this->flattenCriteriaCollections($criteriaCollections);

        foreach ($criteriaCollection as $definition => $criteriaObjects) {
            $directReads[$definition] = [[]];
            $searches[$definition] = [];

            foreach ($criteriaObjects as $criteria) {
                if ($this->canBeMerged($criteria)) {
                    $directReads[$definition][] = $criteria->getIds();
                } else {
                    $criteriaHash = $this->hash($criteria);
                    $criteria->addExtension('criteriaHash', new ArrayEntity(['hash' => $criteriaHash]));
                    $searches[$definition][$criteriaHash] = $criteria;
                }
            }
        }

        foreach ($directReads as $definition => $idLists) {
            /** @var array<string, array<string>> $directReads */
            $directReads[$definition] = array_merge(...$idLists);
        }

        return [
            array_filter($directReads),
            array_filter($searches),
        ];
    }

    private function canBeMerged(Criteria $criteria): bool
    {
        // paginated lists must be an own search
        if ($criteria->getOffset() !== null || $criteria->getLimit() !== null) {
            return false;
        }

        // sortings must be an own search
        if (\count($criteria->getSorting())) {
            return false;
        }

        // queries must be an own search
        if (\count($criteria->getQueries())) {
            return false;
        }

        if ($criteria->getAssociations()) {
            return false;
        }

        if ($criteria->getAggregations()) {
            return false;
        }

        $filters = array_merge(
            $criteria->getFilters(),
            $criteria->getPostFilters()
        );

        // any kind of filters must be an own search
        if (!empty($filters)) {
            return false;
        }

        if (empty($criteria->getIds())) {
            return false;
        }

        return true;
    }

    private function getApiRepository(EntityDefinition $definition): EntityRepository
    {
        return $this->definitionRegistry->getRepository($definition->getEntityName());
    }

    private function getSalesChannelApiRepository(EntityDefinition $definition): ?SalesChannelRepository
    {
        return $this->repositories[$definition->getEntityName()] ?? null;
    }

    /**
     * @param array<string, CriteriaCollection> $criteriaCollections
     *
     * @return array<string, array<Criteria>>
     */
    private function flattenCriteriaCollections(array $criteriaCollections): array
    {
        $flattened = [];

        $criteriaCollections = array_values($criteriaCollections);

        foreach ($criteriaCollections as $collections) {
            foreach ($collections as $definition => $criteriaObjects) {
                $flattened[$definition] = array_merge($flattened[$definition] ?? [], array_values($criteriaObjects));
            }
        }

        return $flattened;
    }

    /**
     * @template TEntityCollection of EntityCollection
     *
     * @param array<string, CriteriaCollection> $criteriaObjects
     * @param array<string, EntitySearchResult<TEntityCollection>> $searchResults
     */
    private function mapSearchResults(ElementDataCollection $result, CmsSlotEntity $slot, array $criteriaObjects, array $searchResults): void
    {
        if (!isset($criteriaObjects[$slot->getUniqueIdentifier()])) {
            return;
        }

        foreach ($criteriaObjects[$slot->getUniqueIdentifier()] as $criterias) {
            foreach ($criterias as $key => $criteria) {
                if (!$criteria->hasExtension('criteriaHash')) {
                    continue;
                }

                /** @var ArrayEntity $hashArrayEntity */
                $hashArrayEntity = $criteria->getExtension('criteriaHash');
                $hash = $hashArrayEntity->get('hash');
                if (!isset($searchResults[$hash])) {
                    continue;
                }

                $result->add($key, $searchResults[$hash]);
            }
        }
    }

    /**
     * @template TEntityCollection of EntityCollection
     *
     * @param array<string, CriteriaCollection> $criteriaObjects
     * @param array<string, EntitySearchResult<TEntityCollection>> $entities
     */
    private function mapEntities(ElementDataCollection $result, CmsSlotEntity $slot, array $criteriaObjects, array $entities): void
    {
        if (!isset($criteriaObjects[$slot->getUniqueIdentifier()])) {
            return;
        }

        foreach ($criteriaObjects[$slot->getUniqueIdentifier()] as $definition => $criterias) {
            foreach ($criterias as $key => $criteria) {
                if (!$this->canBeMerged($criteria)) {
                    continue;
                }

                if (!isset($entities[$definition])) {
                    continue;
                }

                $ids = $criteria->getIds();
                $filtered = $entities[$definition]->filter(fn (Entity $entity) => \in_array($entity->getUniqueIdentifier(), $ids, true));

                $result->add($key, $filtered);
            }
        }
    }

    private function hash(Criteria $criteria): string
    {
        return Hasher::hash($criteria);
    }
}
