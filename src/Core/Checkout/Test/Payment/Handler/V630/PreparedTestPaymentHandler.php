<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler\V630;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CapturePreparedPaymentException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class PreparedTestPaymentHandler implements PreparedPaymentHandlerInterface
{
    final public const TEST_STRUCT_CONTENT = ['testValue'];

    public static ?Struct $preOrderPaymentStruct = null;

    public static bool $fail = false;

    public function validate(
        Cart $cart,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context
    ): Struct {
        if (self::$fail) {
            throw new ValidatePreparedPaymentException('this is supposed to fail');
        }

        self::$preOrderPaymentStruct = null;

        return new ArrayStruct(self::TEST_STRUCT_CONTENT);
    }

    public function capture(
        PreparedPaymentTransactionStruct $transaction,
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        Struct $preOrderPaymentStruct
    ): void {
        if (self::$fail) {
            throw new CapturePreparedPaymentException($transaction->getOrderTransaction()->getId(), 'this is supposed to fail');
        }

        self::$preOrderPaymentStruct = $preOrderPaymentStruct;
    }
}
