<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Shopware\Core\Checkout\CheckoutContext;

interface CheckoutContextFactoryInterface
{
    public function create(
        string $tenantId,
        string $token,
        string $salesChannelId,
        array $options = []
    ): CheckoutContext;
}
