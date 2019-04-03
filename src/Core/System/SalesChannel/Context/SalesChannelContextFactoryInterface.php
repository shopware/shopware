<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\CheckoutContext;

interface SalesChannelContextFactoryInterface
{
    public function create(
        string $token,
        string $salesChannelId,
        array $options = []
    ): CheckoutContext;
}
