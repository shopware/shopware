<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<PaymentMethodCollection>>
 */
#[Package('checkout')]
class PaymentMethodRouteResponse extends StoreApiResponse
{
    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->object->getEntities();
    }
}
