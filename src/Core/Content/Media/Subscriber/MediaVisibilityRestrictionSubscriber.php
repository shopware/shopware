<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Sanitizer\AbstractCriteriaSanitizer;
use Shopware\Core\Content\Media\Sanitizer\MediaCriteriaSanitizer;
use Shopware\Core\Content\Media\Sanitizer\MediaFolderCriteriaSanitizer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregatedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\BucketAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class MediaVisibilityRestrictionSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => 'securePrivateFolders',
            EntityAggregatedEvent::class => 'securePrivateMediaAggregation',
        ];
    }

    public function securePrivateFolders(EntitySearchedEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        match ($event->getDefinition()->getEntityName()) {
            MediaFolderDefinition::ENTITY_NAME => $this->addMediaFolderRestriction($event->getCriteria()),
            MediaDefinition::ENTITY_NAME => $this->addMediaRestriction($event->getCriteria()),
            default => null,
        };
    }

    public function securePrivateMediaAggregation(EntityAggregatedEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        match ($event->getDefinition()->getEntityName()) {
            MediaFolderDefinition::ENTITY_NAME => $this->sanitizeAllAggregations($event->getCriteria(), new MediaFolderCriteriaSanitizer()),
            MediaDefinition::ENTITY_NAME => $this->sanitizeAllAggregations($event->getCriteria(), new MediaCriteriaSanitizer()),
            default => null,
        };
    }

    private function addMediaFolderRestriction(Criteria $criteria): void
    {
        $mediaFolderCriteriaSanitizer = new MediaFolderCriteriaSanitizer();
        $criteria->addFilter($mediaFolderCriteriaSanitizer->getFilterReplacement());
        $this->sanitizeAllAggregations($criteria, $mediaFolderCriteriaSanitizer);
    }

    private function addMediaRestriction(Criteria $criteria): void
    {
        $mediaCriteriaSanitizer = new MediaCriteriaSanitizer();
        $criteria->addFilter($mediaCriteriaSanitizer->getFilterReplacement());

        $this->sanitizeAllAggregations($criteria, $mediaCriteriaSanitizer);
    }

    private function sanitizeAllAggregations(Criteria $criteria, AbstractCriteriaSanitizer $sanitizer): void
    {
        if (\count($criteria->getAggregations()) === 0) {
            return;
        }

        $saneAggregations = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $saneAggregations[] = $this->sanitizeAggregation($aggregation, $sanitizer);
        }
        $criteria->resetAggregations();
        $criteria->addAggregation(...$saneAggregations);
    }

    private function sanitizeAggregation(Aggregation $aggregation, AbstractCriteriaSanitizer $sanitizer): Aggregation
    {
        return match ($aggregation::class) {
            FilterAggregation::class => $this->sanitizeFilterAggregation($aggregation, $sanitizer),
            BucketAggregation::class => $this->sanitizeBucketAggregation($aggregation, $sanitizer),
            default => $aggregation,
        };
    }

    private function sanitizeFilterAggregation(FilterAggregation $filterAggregation, AbstractCriteriaSanitizer $sanitizer): FilterAggregation
    {
        return new FilterAggregation(
            $filterAggregation->getName(),
            $this->sanitizeAggregation($filterAggregation->getAggregation(), $sanitizer),
            $this->sanitizeAggregationFilters($filterAggregation, $sanitizer)
        );
    }

    /**
     * @return list<Filter>
     */
    private function sanitizeAggregationFilters(FilterAggregation $filterAggregation, AbstractCriteriaSanitizer $sanitizer): array
    {
        $saneFilters = [];
        foreach ($filterAggregation->getFilter() as $filter) {
            if (!$filter instanceof MultiFilter) {
                $saneFilters[] = $filter;
                continue;
            }
            $saneFilters[] = new MultiFilter(
                $filter->getOperator(),
                $this->sanitizeAggregationFilterQueries($filter, $sanitizer)
            );
        }

        return $saneFilters;
    }

    /**
     * @return list<Filter>
     */
    private function sanitizeAggregationFilterQueries(MultiFilter $filter, AbstractCriteriaSanitizer $sanitizer): array
    {
        $saneQueries = [];
        foreach ($filter->getQueries() as $query) {
            /**
             * Cannot check for {@see SingleFieldFilter}, as {@see NotEqualsAnyFilter} wraps a {@see SingleFieldFilter},
             * but extends {@see NotFilter}. Need to check all fields instead of just one because of that. Checking the
             * end of the string to prevent using joins to bypass the restriction.
             */
            $containsRelevantField = \array_filter(
                $query->getFields(),
                fn (string $field) => $sanitizer->shouldSanitizeField($field)
            );
            if (\count($containsRelevantField) === 0) {
                $saneQueries[] = $query;
                continue;
            }

            // If the attacker tries to negate the check for the private flag, just ignore the whole filter.
            if ($query instanceof NotFilter) {
                $saneQueries[] = $sanitizer->getFilterReplacement();
                continue;
            }

            // Need the JSON representation as this is the lowest common denominator for all filter types.
            $filterVariables = $query->jsonSerialize();
            // Unsure what kind of filter could not have a value but still filter the private flag.
            if ($sanitizer->shouldSanitizeValue($filterVariables['value'] ?? null)) {
                $saneQueries[] = $sanitizer->getFilterReplacement();
                continue;
            }
            $saneQueries[] = $query;
        }

        return $saneQueries;
    }

    private function sanitizeBucketAggregation(BucketAggregation $bucketAggregation, AbstractCriteriaSanitizer $sanitizer): BucketAggregation
    {
        return new BucketAggregation(
            $bucketAggregation->getName(),
            $bucketAggregation->getField(),
            $this->sanitizeAggregation($bucketAggregation->getAggregation(), $sanitizer),
        );
    }
}
