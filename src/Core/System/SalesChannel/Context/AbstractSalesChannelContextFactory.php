<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @phpstan-type SalesChannelContextFactoryPrimitiveOptions array{cartToken?: ?CartToken, currencyId?: ?string, languageId?: ?string, customerId?: ?string, customerGroupId?: ?string, billingAddressId?: ?string, shippingAddressId?: ?string, paymentMethodId?: ?string, shippingMethodId?: ?string, countryId?: ?string, countryStateId?: ?string, versionId?: ?string, permissions?: ?array<string, bool>, domainId?: ?string}
 * @phpstan-type SalesChannelContextFactoryOptions SalesChannelContextFactoryPrimitiveOptions&array{originalContext?: ?Context}
 */
#[Package('framework')]
abstract class AbstractSalesChannelContextFactory
{
    abstract public function getDecorated(): AbstractSalesChannelContextFactory;

    /**
     * @param ContextToken $token
     * @param SalesChannelContextFactoryOptions $options
     */
    abstract public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext;
}
