<?php declare(strict_types=1);

namespace Shopware\Core\Service\Permission;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface RemoteLogger
{
    public function log(PermissionsConsent $consent, ConsentState $state): void;
}
