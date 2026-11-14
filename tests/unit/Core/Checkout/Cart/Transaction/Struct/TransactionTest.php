<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Transaction\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Transaction::class)]
class TransactionTest extends TestCase
{
    public function testIdentifierNamesTheTransactionInTheApi(): void
    {
        $transaction = $this->createTransaction('gift-card');

        static::assertSame('gift-card', $transaction->getIdentifier());
        static::assertSame('gift-card', $transaction->jsonSerialize()['identifier']);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the null default of the identifier
     */
    public function testConstructingWithoutIdentifierThrowsWithTheMajorFlag(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $identifier to ' . Transaction::class . ' is deprecated, it will be required in v6.8.0.0.'
        ));

        $this->createTransaction(null);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testTransactionWithoutIdentifierHasNone(): void
    {
        static::assertNull($this->createTransaction(null)->getIdentifier());
    }

    public function testTransactionOfACartPersistedBeforeIdentifiersExistedHasNone(): void
    {
        $transaction = Serialization::assertUnserializedInstanceOf(Transaction::class, \sprintf('O:%d:"%s":0:{}', \strlen(Transaction::class), Transaction::class));

        static::assertNull($transaction->getIdentifier());
    }

    private function createTransaction(?string $identifier): Transaction
    {
        return new Transaction(
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'payment-method-id',
            $identifier
        );
    }
}
