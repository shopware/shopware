<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Media\Event\MediaBasicLoadedEvent;
use Shopware\ProductMedia\Struct\ProductMediaBasicCollection;

class ProductMediaBasicLoadedEvent extends NestedEvent
{
    const NAME = 'productMedia.basic.loaded';

    /**
     * @var ProductMediaBasicCollection
     */
    protected $productMedias;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductMediaBasicCollection $productMedias, TranslationContext $context)
    {
        $this->productMedias = $productMedias;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductMedias(): ProductMediaBasicCollection
    {
        return $this->productMedias;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productMedias->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->productMedias->getMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
