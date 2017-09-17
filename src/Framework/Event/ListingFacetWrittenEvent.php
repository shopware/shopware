<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ListingFacetWrittenEvent extends NestedEvent
{
    const NAME = 'listing_facet.written';

    /**
     * @var string[]
     */
    private $listingFacetUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $listingFacetUuids, array $errors = [])
    {
        $this->listingFacetUuids = $listingFacetUuids;
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
    public function getListingFacetUuids(): array
    {
        return $this->listingFacetUuids;
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
