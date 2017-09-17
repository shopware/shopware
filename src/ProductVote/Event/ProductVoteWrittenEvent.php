<?php declare(strict_types=1);

namespace Shopware\ProductVote\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductVoteWrittenEvent extends NestedEvent
{
    const NAME = 'product_vote.written';

    /**
     * @var string[]
     */
    private $productVoteUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $productVoteUuids, array $errors = [])
    {
        $this->productVoteUuids = $productVoteUuids;
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
    public function getProductVoteUuids(): array
    {
        return $this->productVoteUuids;
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
