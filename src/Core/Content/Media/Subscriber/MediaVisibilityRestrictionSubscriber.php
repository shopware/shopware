<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
            BeforeEntityAggregationEvent::class => 'securePrivateMediaAggregation',
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

    public function securePrivateMediaAggregation(BeforeEntityAggregationEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        match ($event->getDefinition()->getEntityName()) {
            MediaFolderDefinition::ENTITY_NAME => $this->sanitizeAllAggregations($event->getCriteria(), $this->getMediaFolderRestriction()),
            MediaDefinition::ENTITY_NAME => $this->sanitizeAllAggregations($event->getCriteria(), $this->getMediaRestriction()),
            default => null,
        };
    }

    private function addMediaFolderRestriction(Criteria $criteria): void
    {
        $criteria->addFilter($this->getMediaFolderRestriction());
        $this->sanitizeAllAggregations($criteria, $this->getMediaFolderRestriction());
    }

    private function addMediaRestriction(Criteria $criteria): void
    {
        $criteria->addFilter($this->getMediaRestriction());

        $this->sanitizeAllAggregations($criteria, $this->getMediaRestriction());
    }

    private function sanitizeAllAggregations(Criteria $criteria, Filter $restrictionFilter): void
    {
        if (\count($criteria->getAggregations()) === 0) {
            return;
        }

        $saneAggregations = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $saneAggregations[] = $this->sanitizeAggregation($aggregation, $restrictionFilter);
        }
        $criteria->resetAggregations();
        $criteria->addAggregation(...$saneAggregations);
    }

    private function sanitizeAggregation(Aggregation $aggregation, Filter $restrictionFilter): Aggregation
    {
        return match ($aggregation::class) {
            FilterAggregation::class => $this->addRestrictionToFilterAggregation($aggregation, $restrictionFilter),
            default => $this->wrapAggregationWithRestriction($aggregation, $restrictionFilter),
        };
    }

    private function addRestrictionToFilterAggregation(FilterAggregation $aggregation, Filter $restrictionFilter): FilterAggregation
    {
        $aggregation->addFilters([$restrictionFilter]);

        return $aggregation;
    }

    private function wrapAggregationWithRestriction(Aggregation $aggregation, Filter $restrictionFilter): FilterAggregation
    {
        return new FilterAggregation(
            'Sanitized ' . $aggregation->getName(),
            $aggregation,
            [$restrictionFilter]
        );
    }

    private function getMediaRestriction(): MultiFilter
    {
        return new MultiFilter('OR', [
            new EqualsFilter('private', false),
            new MultiFilter('AND', [
                new EqualsFilter('private', true),
                new EqualsFilter('mediaFolder.defaultFolder.entity', 'product_download'),
            ]),
        ]);
    }

    private function getMediaFolderRestriction(): MultiFilter
    {
        return new MultiFilter('OR', [
            new EqualsFilter('media_folder.configuration.private', false),
            new EqualsFilter('media_folder.configuration.private', null),
        ]);
    }
}
