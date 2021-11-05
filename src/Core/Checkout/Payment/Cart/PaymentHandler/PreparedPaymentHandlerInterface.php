<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\CapturePreparedPaymentException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\Request;

interface PreparedPaymentHandlerInterface
{
    /**
     * @throws ValidatePreparedPaymentException
     */
    public function validate(Request $request, Cart $cart): Struct;

    /**
     * @throws CapturePreparedPaymentException
     */
    public function capture(Request $request, OrderEntity $order, Struct $preOrderPaymentStruct): void;
}
