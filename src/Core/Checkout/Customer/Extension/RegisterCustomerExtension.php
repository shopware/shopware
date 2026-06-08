<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps `RegisterRoute::register`. A listener on the `.pre` event may inspect the
 * submitted data and abort the registration by throwing, or fully replace the
 * registration by assigning `$result` and calling `stopPropagation()`.
 *
 * @extends Extension<CustomerResponse>
 */
#[Package('checkout')]
final class RegisterCustomerExtension extends Extension
{
    public const NAME = 'account.register';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted registration data
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
        /**
         * @public
         *
         * @description Additional validation definitions to merge into the registration validation
         */
        public readonly ?DataValidationDefinition $additionalValidationDefinitions = null,
    ) {
    }
}
