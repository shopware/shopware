<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps `CustomerRecoveryIsExpiredRoute::load`. A listener on the `.pre` event
 * may resolve the expiry check itself (e.g. for an alternative account store),
 * assign a `CustomerRecoveryIsExpiredResponse` to `$result` and call
 * `stopPropagation()` to short-circuit the core flow.
 *
 * @extends Extension<CustomerRecoveryIsExpiredResponse>
 */
#[Package('checkout')]
final class RecoveryIsExpiredExtension extends Extension
{
    public const NAME = 'account.recovery-is-expired';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted data (contains the recovery hash)
         */
        public readonly RequestDataBag $data,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly SalesChannelContext $context,
    ) {
    }
}
