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
    public function testProtectedPayloadValuesStayOutOfTheSerializedStruct(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setPayload(['installments' => 3, 'psp' => 'ref-1'], ['psp' => true]);

        static::assertSame(['installments' => 3], $transaction->jsonSerialize()['payload']);
    }

    private function createTransaction(): Transaction
    {
        return new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id');
    }
}
