<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * Wraps `SendPasswordRecoveryMailRoute::sendRecoveryMail`. A listener on the
 * `.pre` event may handle the recovery request itself (e.g. for an alternative
 * account store), assign a `SuccessResponse` to `$result` and call
 * `stopPropagation()` to short-circuit the core flow.
 *
 * @extends Extension<SuccessResponse>
 */
#[Package('checkout')]
final class SendRecoveryMailExtension extends Extension
{
    public const NAME = 'account.send-recovery-mail';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted recovery data (contains the e-mail address)
         */
        public readonly RequestDataBag $data,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Whether the submitted storefrontUrl is validated against the sales-channel domains
         */
        public readonly bool $validateStorefrontUrl = true,
    ) {
    }
}
