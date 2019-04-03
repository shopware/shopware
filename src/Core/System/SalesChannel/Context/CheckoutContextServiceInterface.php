<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\CheckoutContext;

interface CheckoutContextServiceInterface
{
    public function get(string $salesChannelId, string $token, ?string $languageId): CheckoutContext;

    public function refresh(string $salesChannelId, string $token, ?string $languageId): void;
}
