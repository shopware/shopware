<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route allows changing configurations inside the context.
 * Following parameters are allowed to change: "currencyId", "languageId", "billingAddressId", "shippingAddressId",
 * "paymentMethodId", "shippingMethodId", "countryId" and "countryStateId"
 */
interface ContextSwitchRouteInterface
{
    public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}
