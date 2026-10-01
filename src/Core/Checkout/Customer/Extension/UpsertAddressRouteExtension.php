<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<UpsertAddressRouteResponse>
 */
#[Package('checkout')]
final class UpsertAddressRouteExtension extends Extension
{
    public const NAME = 'upsert-address-route.upsert';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly ?string $addressId,
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
        public readonly CustomerEntity $customer,
    ) {
    }
}
