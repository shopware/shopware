<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CustomerResponse>
 */
#[Package('checkout')]
final class RegisterConfirmRouteExtension extends Extension
{
    public const NAME = 'register-confirm-route.confirm';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly RequestDataBag $dataBag,
        public readonly SalesChannelContext $context,
    ) {
    }
}
