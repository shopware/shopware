<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class PaymentMethodRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult
     */
    protected $object;

    public function __construct(EntitySearchResult $paymentMethods)
    {
        parent::__construct($paymentMethods);
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        /** @var PaymentMethodCollection $collection */
        $collection = $this->object->getEntities();

        return $collection;
    }
}
