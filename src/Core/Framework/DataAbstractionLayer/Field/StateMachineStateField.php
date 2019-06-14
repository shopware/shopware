<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class StateMachineStateField extends FkField
{
    /**
     * @var string
     */
    private $stateMachineName;

    public function __construct(string $storageName, string $propertyName, string $stateMachineName)
    {
        $this->stateMachineName = $stateMachineName;
        $this->addFlags(new WriteProtected(Context::SYSTEM_SCOPE));

        parent::__construct($storageName, $propertyName, StateMachineStateDefinition::class);
    }

    public function getStateMachineName(): string
    {
        return $this->stateMachineName;
    }
}
