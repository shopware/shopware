<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\Cart\PaymentMethodValidator;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodValidator::class)]
class PaymentMethodValidatorTest extends TestCase
{
    private PaymentMethodValidator $validator;

    private Cart $cart;

    protected function setUp(): void
    {
        $this->validator = new PaymentMethodValidator();
        $this->cart = new Cart('cart-token');
    }

    public function testValidateWithoutErrors(): void
    {
        $context = $this->getSalesChannelContext();
        $errors = new ErrorCollection();

        $this->validator->validate($this->cart, $errors, $context);

        static::assertCount(0, $errors, \print_r($errors, true));
    }

    public function testValidatePaymentMethodIsInactive(): void
    {
        $context = $this->getSalesChannelContext();
        $context->getPaymentMethod()->setActive(false);

        $errors = new ErrorCollection();

        $this->validator->validate($this->cart, $errors, $context);

        static::assertCount(1, $errors);
        $error = $errors->get('payment-method-blocked-1');
        static::assertNotNull($error);
        static::assertStringContainsString('inactive', $error->getMessage(), print_r($error->getMessage(), true));
    }

    public function testValidatePaymentMethodNotAvailableInSalesChannel(): void
    {
        $context = $this->getSalesChannelContext();
        $context->getSalesChannel()->setPaymentMethodIds([]);

        $errors = new ErrorCollection();

        $this->validator->validate($this->cart, $errors, $context);

        static::assertCount(1, $errors);
        $error = $errors->get('payment-method-blocked-1');
        static::assertNotNull($error);
        static::assertStringContainsString('not allowed', $error->getMessage());
    }

    public function testValidateAvailabilityRuleNotMatched(): void
    {
        $context = $this->getSalesChannelContext();
        $context->setRuleIds([]);

        $errors = new ErrorCollection();

        $this->validator->validate($this->cart, $errors, $context);

        static::assertCount(1, $errors);
        $error = $errors->get('payment-method-blocked-1');
        static::assertNotNull($error);
        static::assertStringContainsString('rule not matching', $error->getMessage());
    }

    public function testValidateAllErrorsTriggeredOnlyContainsLastError(): void
    {
        $context = $this->getSalesChannelContext();
        $context->getPaymentMethod()->setActive(false);
        $context->getSalesChannel()->setPaymentMethodIds([]);
        $context->setRuleIds([]);

        $errors = new ErrorCollection();

        $this->validator->validate($this->cart, $errors, $context);

        static::assertCount(1, $errors);
        $error = $errors->get('payment-method-blocked-1');
        static::assertNotNull($error);
    }

    /**
     * @param list<string> $transactionPaymentMethodIds
     */
    #[DataProvider('transactionIndexProvider')]
    public function testBlockedErrorReferencesTheTransactionOfThePaymentMethod(array $transactionPaymentMethodIds, ?int $expectedTransactionIndex): void
    {
        $amount = new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $this->cart->setTransactions(new TransactionCollection(array_map(
            static fn (string $paymentMethodId) => new Transaction($amount, $paymentMethodId),
            $transactionPaymentMethodIds,
        )));

        $context = $this->getSalesChannelContext();
        $context->getPaymentMethod()->setActive(false);

        $errors = new ErrorCollection();

        $this->validator->validate($this->cart, $errors, $context);

        $error = $errors->get('payment-method-blocked-1');
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame($expectedTransactionIndex, $error->getTransactionIndex());
    }

    public static function transactionIndexProvider(): \Generator
    {
        yield 'cart without transactions, as during the cart calculation, references none' => [[], null];
        yield 'first transaction paid with the blocked method is referenced' => [['other', '1', '1'], 1];
        yield 'transactions paid with other methods are not referenced' => [['other'], null];
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('1');
        $paymentMethod->setActive(true);
        $paymentMethod->setAvailabilityRuleId('payment-method-availability-rule-id');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setPaymentMethodIds(['1']);

        $base = Context::createDefaultContext();
        $base->setRuleIds(['payment-method-availability-rule-id']);

        return Generator::generateSalesChannelContext(
            baseContext: $base,
            salesChannel: $salesChannel,
            paymentMethod: $paymentMethod,
        );
    }
}
