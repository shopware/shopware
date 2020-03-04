<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class PaymentMethodRouteResponse extends StoreApiResponse
{
    /**
     * @var PaymentMethodCollection
     */
    protected $object;

    public function __construct(PaymentMethodCollection $paymentMethods)
    {
        parent::__construct($paymentMethods);
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->object;
    }
}
