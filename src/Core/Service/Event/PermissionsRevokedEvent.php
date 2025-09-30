<?php declare(strict_types=1);

namespace Shopware\Core\Service\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Permission\PermissionsConsent;

/**
 * @internal
 */
#[Package('framework')]
readonly class PermissionsRevokedEvent implements ShopwareEvent
{
    public function __construct(
        public PermissionsConsent $permissionsConsent,
        public Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
