<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ListingFacetTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'listing_facet_translation.written';

    /**
     * @var string[]
     */
    private $listingFacetTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $listingFacetTranslationUuids, array $errors = [])
    {
        $this->listingFacetTranslationUuids = $listingFacetTranslationUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getListingFacetTranslationUuids(): array
    {
        return $this->listingFacetTranslationUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
