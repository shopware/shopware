<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SlotDataResolver
{
    /**
     * @var SlotTypeDataResolverInterface[]
     */
    private $resolvers;

    /**
     * @var array
     */
    private $repositories;

    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    /**
     * @param SlotTypeDataResolverInterface[] $resolvers
     */
    public function __construct(iterable $resolvers, array $repositories, DefinitionRegistry $definitionRegistry)
    {
        $this->definitionRegistry = $definitionRegistry;

        foreach ($repositories as $entityName => $repository) {
            $this->repositories[$entityName] = $repository;
        }

        foreach ($resolvers as $resolver) {
            $this->resolvers[$resolver->getType()] = $resolver;
        }
    }

    public function resolve(CmsSlotCollection $slots, ResolverContext $resolverContext): CmsSlotCollection
    {
        $slotCriteriaList = [];

        /*
         * Collect criteria objects for each slot from resolver
         *
         * @var CmsSlotEntity
         */
        foreach ($slots as $slot) {
            $resolver = $this->resolvers[$slot->getType()] ?? null;
            if (!$resolver) {
                continue;
            }

            $collection = $resolver->collect($slot, $resolverContext);
            if ($collection === null) {
                continue;
            }

            $slotCriteriaList[$slot->getUniqueIdentifier()] = $collection;
        }

        // reduce search requests by combining mergeable criteria objects
        [$directReads, $searches] = $this->optimizeCriteriaObjects($slotCriteriaList);

        // fetch data from storage
        $entities = $this->fetchByIdentifier($directReads, $resolverContext->getSalesChannelContext());
        $searchResults = $this->fetchByCriteria($searches, $resolverContext->getSalesChannelContext());

        // create result for each slot with the requested data
        foreach ($slots as $slotId => $slot) {
            $resolver = $this->resolvers[$slot->getType()] ?? null;
            if (!$resolver) {
                continue;
            }

            $result = new SlotDataResolveResult();

            $this->mapSearchResults($result, $slot, $slotCriteriaList, $searchResults);
            $this->mapEntities($result, $slot, $slotCriteriaList, $entities);

            $resolver->enrich($slot, $resolverContext, $result);

            // replace with return value from enrich(), because it's allowed to change the entity type
            $slots->set($slotId, $slot);
        }

        return $slots;
    }

    /**
     * @param string[][] $directReads
     *
     * @throws InconsistentCriteriaIdsException
     *
     * @return EntitySearchResult[]
     */
    private function fetchByIdentifier(array $directReads, SalesChannelContext $context): array
    {
        $entities = [];

        foreach ($directReads as $definition => $ids) {
            $repository = $this->getSalesChannelApiRepository($definition);
            if ($repository) {
                $entities[$definition] = $repository->search(new Criteria($ids), $context);
            } else {
                $repository = $this->getApiRepository($definition);
                $entities[$definition] = $repository->search(new Criteria($ids), $context->getContext());
            }
        }

        return $entities;
    }

    private function fetchByCriteria(array $searches, SalesChannelContext $context): array
    {
        $searchResults = [];

        /** @var Criteria[] $criteriaObjects */
        foreach ($searches as $definition => $criteriaObjects) {
            foreach ($criteriaObjects as $criteriaHash => $criteria) {
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
     * @param CriteriaCollection[] $criteriaCollections
     */
    private function optimizeCriteriaObjects(array $criteriaCollections): array
    {
        $directReads = [];
        $searches = [];

        $criteriaCollection = $this->flattenCriteriaCollections($criteriaCollections);

        foreach ($criteriaCollection as $definition => $criteriaObjects) {
            $directReads[$definition] = [[]];
            $searches[$definition] = [];

            /** @var Criteria $criteria */
            foreach ($criteriaObjects as $key => $criteria) {
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
            $directReads[$definition] = array_merge(...$idLists);
        }

        return [
            array_filter($directReads),
            array_filter($searches),
        ];
    }

    private function canBeMerged(Criteria $criteria): bool
    {
        //paginated lists must be an own search
        if ($criteria->getOffset() !== null || $criteria->getLimit() !== null) {
            return false;
        }

        //sortings must be an own search
        if (\count($criteria->getSorting())) {
            return false;
        }

        //queries must be an own search
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

        if (empty($filters) && empty($criteria->getIds())) {
            return false;
        }

        return true;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function getApiRepository(string $definition): EntityRepositoryInterface
    {
        return $this->definitionRegistry->getRepository($definition::getEntityName());
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return mixed|null
     */
    private function getSalesChannelApiRepository(string $definition)
    {
        return $this->repositories[$definition::getEntityName()] ?? null;
    }

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
     * @param CriteriaCollection[] $criteriaObjects
     * @param EntitySearchResult[] $searchResults
     */
    private function mapSearchResults(SlotDataResolveResult $result, CmsSlotEntity $slot, array $criteriaObjects, array $searchResults): void
    {
        if (!isset($criteriaObjects[$slot->getUniqueIdentifier()])) {
            return;
        }

        foreach ($criteriaObjects[$slot->getUniqueIdentifier()] as $definition => $criterias) {
            foreach ($criterias as $key => $criteria) {
                if (!$criteria->hasExtension('criteriaHash')) {
                    continue;
                }

                $hash = $criteria->getExtension('criteriaHash')->get('hash');
                if (!isset($searchResults[$hash])) {
                    continue;
                }

                $result->add($key, $searchResults[$hash]);
            }
        }
    }

    /**
     * @param CriteriaCollection[] $criteriaObjects
     * @param EntitySearchResult[] $entities
     */
    private function mapEntities(SlotDataResolveResult $result, CmsSlotEntity $slot, array $criteriaObjects, array $entities): void
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
                $filtered = $entities[$definition]->filter(function (Entity $entity) use ($ids) {
                    return in_array($entity->getUniqueIdentifier(), $ids, true);
                });

                $result->add($key, $filtered);
            }
        }
    }

    private function hash(Criteria $criteria): string
    {
        return md5(serialize($criteria));
    }
}
