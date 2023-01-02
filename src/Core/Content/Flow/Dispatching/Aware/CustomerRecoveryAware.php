<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
#[Package('business-ops')]
interface CustomerRecoveryAware extends FlowEventAware
{
    public const CUSTOMER_RECOVERY_ID = 'customerRecoveryId';

    public const CUSTOMER_RECOVERY = 'customerRecovery';

    public function getCustomerRecoveryId(): string;
}
