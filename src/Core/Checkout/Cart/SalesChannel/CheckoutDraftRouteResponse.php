<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CheckoutDraft>
 */
#[Package('checkout')]
class CheckoutDraftRouteResponse extends StoreApiResponse
{
    public function getDraft(): CheckoutDraft
    {
        return $this->object;
    }
}
