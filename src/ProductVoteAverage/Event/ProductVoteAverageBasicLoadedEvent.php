<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;

class ProductVoteAverageBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product_vote_average_ro.basic.loaded';

    /**
     * @var ProductVoteAverageBasicCollection
     */
    protected $productVoteAverages;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductVoteAverageBasicCollection $productVoteAverages, TranslationContext $context)
    {
        $this->productVoteAverages = $productVoteAverages;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductVoteAverages(): ProductVoteAverageBasicCollection
    {
        return $this->productVoteAverages;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
