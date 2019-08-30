<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

class Transition
{
    /**
     * @var string
     */
    private $entityName;

    /**
     * @var string
     */
    private $entityId;

    /**
     * @var string
     */
    private $transitionName;

    /**
     * @var string
     */
    private $stateFieldName;

    public function __construct(
        string $entityName,
        string $entityId,
        string $transitionName,
        string $stateFieldName
    ) {
        $this->entityName = $entityName;
        $this->entityId = $entityId;
        $this->transitionName = $transitionName;
        $this->stateFieldName = $stateFieldName;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getTransitionName(): string
    {
        return $this->transitionName;
    }

    public function getStateFieldName(): string
    {
        return $this->stateFieldName;
    }
}
