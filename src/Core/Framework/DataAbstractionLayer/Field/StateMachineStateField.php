<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StateMachineStateFieldSerializer;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class StateMachineStateField extends FkField
{
    /**
     * @var string
     */
    private $stateMachineName;

    /**
     * @var array
     */
    private $allowedWriteScopes;

    /**
     * @param array $allowedWriteScopes List of scopes, for which changing the status value is still allowed without
     *                                  using the StateMachine
     */
    public function __construct(
        string $storageName,
        string $propertyName,
        string $stateMachineName,
        array $allowedWriteScopes = [Context::SYSTEM_SCOPE]
    ) {
        $this->stateMachineName = $stateMachineName;
        $this->allowedWriteScopes = $allowedWriteScopes;

        parent::__construct($storageName, $propertyName, StateMachineStateDefinition::class);
    }

    public function getStateMachineName(): string
    {
        return $this->stateMachineName;
    }

    public function getAllowedWriteScopes(): array
    {
        return $this->allowedWriteScopes;
    }

    public function getSerializerClass(): string
    {
        return StateMachineStateFieldSerializer::class;
    }
}
