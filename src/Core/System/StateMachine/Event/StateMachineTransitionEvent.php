<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[Package('checkout')]
class StateMachineTransitionEvent extends NestedEvent
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $entityName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $entityId;

    /**
     * @var StateMachineStateEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $fromPlace;

    /**
     * @var StateMachineStateEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $toPlace;

    /**
     * @var Context
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $context;

    public function __construct(
        string $entityName,
        string $entityId,
        StateMachineStateEntity $fromPlace,
        StateMachineStateEntity $toPlace,
        Context $context
    ) {
        $this->entityName = $entityName;
        $this->entityId = $entityId;
        $this->fromPlace = $fromPlace;
        $this->toPlace = $toPlace;
        $this->context = $context;
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
}
