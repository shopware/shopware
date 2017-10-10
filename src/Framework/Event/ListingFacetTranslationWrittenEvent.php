<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\DependencyInjection\Container;

class ListingFacetTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'listing_facet_translation.written';

    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var string[]
     */
    protected $listingFacetUuids = [];
    /**
     * @var string[]
     */
    protected $languageUuids = [];

    /**
     * @var array
     */
    private $rawData;

    public function __construct(array $primaryKeys, TranslationContext $context, array $rawData = [], array $errors = [])
    {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->rawData = $rawData;

        foreach ($primaryKeys as $key => $value) {
            if ($key === 'uuid') {
                $key = 'ListingFacetTranslationUuid';
            }

            $key = lcfirst(Container::camelize($key)) . 's';
            $this->$key = $value;
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
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

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getListingFacetUuids(): array
    {
        return $this->listingFacetUuids;
    }

    public function getLanguageUuids(): array
    {
        return $this->languageUuids;
    }
}
