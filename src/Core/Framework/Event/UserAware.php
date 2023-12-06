<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
#[IsFlowEventAware]
interface UserAware
{
    public const USER_RECOVERY = 'userRecovery';

    public const USER_RECOVERY_ID = 'userRecoveryId';

    public function getUserId(): string;
}
