<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * Wraps `ResetPasswordRoute::resetPassword`. A listener on the `.pre` event may
 * resolve the reset itself (e.g. for an alternative account store), assign a
 * `SuccessResponse` to `$result` and call `stopPropagation()` to short-circuit
 * the core flow.
 *
 * @extends Extension<SuccessResponse>
 */
#[Package('checkout')]
final class ResetPasswordExtension extends Extension
{
    public const NAME = 'account.reset-password';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted reset data (contains hash and new password)
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
