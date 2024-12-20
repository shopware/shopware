<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentMethodValidator;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
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
        $error = $errors->get('payment-method-blocked-');
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
        $error = $errors->get('payment-method-blocked-');
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
        $error = $errors->get('payment-method-blocked-');
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
        $error = $errors->get('payment-method-blocked-');
        static::assertNotNull($error);
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment-method-id');
        $paymentMethod->setActive(true);
        $paymentMethod->setAvailabilityRuleId('payment-method-availability-rule-id');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setPaymentMethodIds(['payment-method-id']);

        $base = Context::createDefaultContext();
        $base->setRuleIds(['payment-method-availability-rule-id']);

        return Generator::createSalesChannelContext(
            baseContext: $base,
            salesChannel: $salesChannel,
            paymentMethod: $paymentMethod,
        );
    }
}
