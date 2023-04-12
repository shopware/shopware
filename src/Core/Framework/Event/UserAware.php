<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - extends of FlowEventAware will be removed
 */
#[Package('system-settings')]
interface UserAware extends FlowEventAware
{
    public const USER_RECOVERY = 'userRecovery';

    public const USER_RECOVERY_ID = 'userRecoveryId';

    public function getUserId(): string;
}
