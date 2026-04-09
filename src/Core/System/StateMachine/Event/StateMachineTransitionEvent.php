<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[Package('checkout')]
class StateMachineTransitionEvent extends NestedEvent
{
    public function __construct(
        protected string $entityName,
        protected string $entityId,
        protected StateMachineStateEntity $fromPlace,
        protected StateMachineStateEntity $toPlace,
        protected Context $context,
        protected ?string $internalComment = null,
    ) {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getFromPlace(): StateMachineStateEntity
    {
        return $this->fromPlace;
    }

    public function getToPlace(): StateMachineStateEntity
    {
        return $this->toPlace;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }
}
