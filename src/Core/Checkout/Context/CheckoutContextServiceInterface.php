<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Shopware\Core\Checkout\CheckoutContext;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
interface CheckoutContextServiceInterface
{
    public function get(string $salesChannelId, string $token): CheckoutContext;

    public function refresh(string $salesChannelId, string $token): void;
}
