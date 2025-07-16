<?php declare(strict_types=1);

namespace Shopware\Core\Service\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Service\Permission\ConsentState;
use Shopware\Core\Service\Permission\PermissionsConsent;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class LogPermissionToRegistryMessage implements AsyncMessageInterface
{
    public function __construct(public readonly PermissionsConsent $permissionsConsent, public readonly ConsentState $consentState)
    {
    }
}
