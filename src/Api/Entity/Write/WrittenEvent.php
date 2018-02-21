<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

abstract class WrittenEvent extends NestedEvent
{
    /**
     * @var NestedEventCollection
     */
    protected $events;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var array
     */
    protected $ids;

    /**
     * @var array
     */
    protected $payload;

    public function __construct(
        array $ids,
        array $payload,
        ShopContext $context,
        array $errors = []
    ) {
        $this->events = new NestedEventCollection();
        $this->context = $context;
        $this->errors = $errors;
        $this->ids = $ids;
        $this->payload = $payload;
    }

    abstract public function getDefinition(): string;

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
