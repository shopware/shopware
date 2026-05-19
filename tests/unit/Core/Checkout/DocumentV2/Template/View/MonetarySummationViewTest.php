<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\AllowanceChargeReason;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\UnitCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MonetarySummationView::class)]
class MonetarySummationViewTest extends TestCase
{
    public function testFromOrderSumsLineAndAllowanceTotalsAndDerivesTax(): void
    {
        $order = $this->createOrder(amountTotal: 100.0, amountNet: 87.03, currency: 'EUR');

        $lineItems = [$this->createLineItem(lineTotal: 84.03)];
        $allowanceCharges = [$this->createCharge(8.0), $this->createAllowance(5.0)];

        $sum = MonetarySummationView::fromOrder($order, $lineItems, $allowanceCharges);

        static::assertSame(84.03, $sum->lineTotal);
        static::assertSame(8.0, $sum->chargeTotal);
        static::assertSame(5.0, $sum->allowanceTotal);
        static::assertSame(87.03, $sum->taxBasisTotal);
        static::assertEqualsWithDelta(12.97, $sum->taxTotal, 0.001);
        static::assertSame(100.0, $sum->grandTotal);
        static::assertSame('EUR', $sum->currencyCode);
        static::assertEqualsWithDelta(0.0, $sum->rounding, 0.001);
    }

    public function testFromOrderTreatsOrderAsUnpaidWithoutTransaction(): void
    {
        $order = $this->createOrder(amountTotal: 100.0, amountNet: 100.0, currency: 'EUR');

        $sum = MonetarySummationView::fromOrder($order, [], []);

        static::assertSame(0.0, $sum->prepaid);
        static::assertSame(100.0, $sum->duePayable);
    }

    public function testFromOrderTreatsOrderAsPaidWhenTransactionStateMatches(): void
    {
        $order = $this->createOrder(amountTotal: 100.0, amountNet: 100.0, currency: 'EUR');
        $order->setPrimaryOrderTransaction($this->createTransactionWithState('paid'));

        $sum = MonetarySummationView::fromOrder($order, [], []);

        static::assertSame(100.0, $sum->prepaid);
        static::assertSame(0.0, $sum->duePayable);
    }

    public function testFromOrderUsesLastTransactionForPaidAmountWhenV68IsInactive(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('v6.7 fallback branch is only exercised when v6.8.0.0 is inactive.');
        }

        $order = $this->createOrder(amountTotal: 100.0, amountNet: 100.0, currency: 'EUR');
        $order->setTransactions(new OrderTransactionCollection([$this->createTransactionWithState('paid')]));

        $sum = MonetarySummationView::fromOrder($order, [], []);

        static::assertSame(100.0, $sum->prepaid);
        static::assertSame(0.0, $sum->duePayable);
    }

    public function testFromOrderThrowsWhenCurrencyMissing(): void
    {
        $order = $this->createOrder(amountTotal: 0.0, amountNet: 0.0, currency: null);

        static::expectExceptionObject(DocumentV2Exception::invalidOrderData(
            $order->getId(),
            'currency',
            'Order has no currency assigned.',
        ));

        MonetarySummationView::fromOrder($order, [], []);
    }

    private function createOrder(float $amountTotal, float $amountNet, ?string $currency): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setAmountTotal($amountTotal);
        $order->setAmountNet($amountNet);

        if ($currency !== null) {
            $currencyEntity = new CurrencyEntity();
            $currencyEntity->setUniqueIdentifier(Uuid::randomHex());
            $currencyEntity->setIsoCode($currency);
            $order->setCurrency($currencyEntity);
        }

        return $order;
    }

    private function createLineItem(float $lineTotal): LineItemView
    {
        return new LineItemView(
            lineId: '1',
            productNumber: null,
            ean: null,
            name: 'item',
            quantity: 1.0,
            basisQuantity: 1.0,
            unitCode: UnitCode::PIECE,
            netUnitPrice: $lineTotal,
            lineTotal: $lineTotal,
            taxCategory: TaxCategory::STANDARD_RATE,
            taxRate: 19.0,
        );
    }

    private function createCharge(float $amount): AllowanceChargeView
    {
        return new AllowanceChargeView(
            isCharge: true,
            actualAmount: $amount,
            basisAmount: null,
            calculationPercent: null,
            reasonCode: AllowanceChargeReason::DELIVERY,
            reason: AllowanceChargeReason::DELIVERY->defaultLabel(),
            taxCategory: TaxCategory::STANDARD_RATE,
            taxRate: 19.0,
        );
    }

    private function createAllowance(float $amount): AllowanceChargeView
    {
        return new AllowanceChargeView(
            isCharge: false,
            actualAmount: $amount,
            basisAmount: null,
            calculationPercent: null,
            reasonCode: AllowanceChargeReason::DISCOUNT,
            reason: 'PROMO',
            taxCategory: TaxCategory::STANDARD_RATE,
            taxRate: 19.0,
        );
    }

    private function createTransactionWithState(string $technicalName): OrderTransactionEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier(Uuid::randomHex());
        $state->setTechnicalName($technicalName);

        $transaction = new OrderTransactionEntity();
        $transaction->setUniqueIdentifier(Uuid::randomHex());
        $transaction->setStateMachineState($state);

        return $transaction;
    }
}
