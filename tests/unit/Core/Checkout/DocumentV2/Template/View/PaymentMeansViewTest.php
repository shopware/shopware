<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\PaymentMeansCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\PaymentMeansView;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PaymentMeansView::class)]
class PaymentMeansViewTest extends TestCase
{
    public function testFromOrderReturnsNullForDebitHandler(): void
    {
        $order = $this->createOrderWithTransaction('payment_debitpayment', 'Debit');

        static::assertNull(PaymentMeansView::fromOrder($order, 'DE89370400440532013000', 'COBADEFFXXX'));
    }

    #[DataProvider('provideHandlerMappings')]
    public function testFromOrderMapsHandlerToCode(string $technicalName, PaymentMeansCode $expected, bool $expectsBankDetails): void
    {
        $order = $this->createOrderWithTransaction($technicalName, 'Friendly Label');

        $view = PaymentMeansView::fromOrder($order, 'DE89370400440532013000', 'COBADEFFXXX');

        static::assertNotNull($view);
        static::assertSame($expected, $view->typeCode);
        static::assertSame('Friendly Label', $view->information);

        if ($expectsBankDetails) {
            static::assertSame('DE89370400440532013000', $view->payeeIban);
            static::assertSame('COBADEFFXXX', $view->payeeBic);
        } else {
            static::assertNull($view->payeeIban);
            static::assertNull($view->payeeBic);
        }
    }

    public static function provideHandlerMappings(): iterable
    {
        yield 'cash on delivery -> CASH' => [
            'technicalName' => 'payment_cashpayment',
            'expected' => PaymentMeansCode::CASH,
            'expectsBankDetails' => false,
        ];

        yield 'invoice -> CREDIT_TRANSFER' => [
            'technicalName' => 'payment_invoicepayment',
            'expected' => PaymentMeansCode::CREDIT_TRANSFER,
            'expectsBankDetails' => true,
        ];

        yield 'prepayment -> CREDIT_TRANSFER' => [
            'technicalName' => 'payment_prepayment',
            'expected' => PaymentMeansCode::CREDIT_TRANSFER,
            'expectsBankDetails' => true,
        ];
    }

    public function testFromOrderReturnsNullForUnknownHandler(): void
    {
        $order = $this->createOrderWithTransaction('payment_defaultpayment', 'Default');

        static::assertNull(PaymentMeansView::fromOrder($order, null, null));
    }

    public function testFromOrderReturnsNullWhenNoTransaction(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        static::assertNull(PaymentMeansView::fromOrder($order, null, null));
    }

    private function createOrderWithTransaction(string $technicalName, string $label): OrderEntity
    {
        $method = new PaymentMethodEntity();
        $method->setUniqueIdentifier(Uuid::randomHex());
        $method->setTechnicalName($technicalName);
        $method->setName($label);

        $transaction = new OrderTransactionEntity();
        $transaction->setUniqueIdentifier(Uuid::randomHex());
        $transaction->setPaymentMethod($method);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrimaryOrderTransaction($transaction);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }
}
