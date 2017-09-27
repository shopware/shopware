<?php declare(strict_types=1);

namespace Shopware\ProductVote\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ProductVote\Struct\ProductVoteBasicCollection;

class ProductVoteBasicLoadedEvent extends NestedEvent
{
    const NAME = 'productVote.basic.loaded';

    /**
     * @var ProductVoteBasicCollection
     */
    protected $productVotes;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductVoteBasicCollection $productVotes, TranslationContext $context)
    {
        $this->productVotes = $productVotes;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProductVotes(): ProductVoteBasicCollection
    {
        return $this->productVotes;
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
