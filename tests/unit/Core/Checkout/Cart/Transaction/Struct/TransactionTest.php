<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Transaction\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Transaction::class)]
class TransactionTest extends TestCase
{
    public function testPayloadIsEmptyByDefault(): void
    {
        $transaction = $this->createTransaction();

        static::assertSame([], $transaction->getPayload());
        static::assertFalse($transaction->hasPayloadValue('psp'));
        static::assertNull($transaction->getPayloadValue('psp'));
    }

    public function testPayloadValuesCanBeSetReadAndRemoved(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setPayload(['psp' => 'ref-1']);
        $transaction->setPayloadValue('installments', 3);

        static::assertSame(['psp' => 'ref-1', 'installments' => 3], $transaction->getPayload());
        static::assertTrue($transaction->hasPayloadValue('installments'));
        static::assertSame(3, $transaction->getPayloadValue('installments'));

        $transaction->removePayloadValue('psp');
        $transaction->removePayloadValue('unknown');

        static::assertSame(['installments' => 3], $transaction->getPayload());
    }

    public function testPayloadIsPartOfTheSerializedStruct(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setPayload(['psp' => 'ref-1']);

        static::assertSame(['psp' => 'ref-1'], $transaction->jsonSerialize()['payload']);
    }

    public function testProtectedPayloadValuesStayOutOfTheSerializedStruct(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setPayload(['installments' => 3, 'psp' => 'ref-1'], ['psp' => true]);
        $transaction->setPayloadValue('riskScore', 42, protected: true);
        $transaction->setPayloadValue('psp', 'ref-2');

        static::assertSame(['installments' => 3, 'psp' => 'ref-2', 'riskScore' => 42], $transaction->getPayload());
        static::assertSame(['installments' => 3], $transaction->jsonSerialize()['payload']);
    }

    public function testPayloadProtectionCanBeLiftedAndEndsWithTheValue(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setPayloadValue('psp', 'ref-1', protected: true);
        $transaction->setPayloadValue('riskScore', 42, protected: true);

        $transaction->setPayloadValue('psp', 'ref-1', protected: false);
        $transaction->removePayloadValue('riskScore');
        $transaction->setPayloadValue('riskScore', 7);

        static::assertSame(['psp' => 'ref-1', 'riskScore' => 7], $transaction->jsonSerialize()['payload']);
    }

    private function createTransaction(): Transaction
    {
        return new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id');
    }
}
