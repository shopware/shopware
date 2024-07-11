<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class StateMachineState extends ForeignKey
{
    public const TYPE = 'state_machine_state';

    public function __construct(
        public string $stateMachineName,
        public array $allowedWriteScopes = [Context::SYSTEM_SCOPE],
        bool|array $api = false,
        ?string $storageName = null
    ) {
        parent::__construct('state_machine_state', $api, $storageName);
        $this->type = self::TYPE;
    }
}
