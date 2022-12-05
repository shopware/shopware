<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

/**
 * @package system-settings
 */
interface UserAware extends FlowEventAware
{
    public const USER_RECOVERY = 'userRecovery';

    public const USER_RECOVERY_ID = 'userRecoveryId';

    public function getUserId(): string;
}
