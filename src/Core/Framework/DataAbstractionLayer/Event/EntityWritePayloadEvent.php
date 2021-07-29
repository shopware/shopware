<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\NestedEvent;

class EntityWritePayloadEvent extends NestedEvent implements GenericEvent
{
    protected array $payload;

    protected Context $context;

    protected EntityDefinition $definition;

    protected string $name;

    public function __construct(EntityDefinition $definition, array $payload, Context $context)
    {
        $this->payload = $payload;
        $this->context = $context;
        $this->definition = $definition;

        $this->name = $this->definition->getEntityName() . '.write.payload';
    }

    /**
     * @param array $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return EntityDefinition
     */
    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
