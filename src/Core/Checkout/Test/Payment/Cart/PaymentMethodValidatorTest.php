<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentMethodValidator;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class PaymentMethodValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var PaymentMethodValidator
     */
    private $validator;

    public function setUp(): void
    {
        $this->validator = $this->getContainer()->get(PaymentMethodValidator::class);
    }

    public function testValidateAvailabilityRuleNotMatched(): void
    {
        $cart = Generator::createCart();
        $context = Generator::createSalesChannelContext();
        $context->getPaymentMethod()->setAvailabilityRuleId(Uuid::randomHex());
        $errors = new ErrorCollection();

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors);
    }

    public function testValidatePaymentMethodNotAvailableInSalesChannel(): void
    {
        $cart = Generator::createCart();

        /** @var EntityRepositoryInterface $salesChannelRepo */
        $salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepo->search(new Criteria([Defaults::SALES_CHANNEL]), Context::createDefaultContext())
            ->get(Defaults::SALES_CHANNEL);

        $context = Generator::createSalesChannelContext(
            null,
            null,
            null,
            $salesChannel
        );

        $errors = new ErrorCollection();

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors);
    }

    public function testValidateWithoutError(): void
    {
        $cart = Generator::createCart();

        /** @var EntityRepositoryInterface $salesChannelRepo */
        $salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        $salesChannel = $salesChannelRepo->search(new Criteria([Defaults::SALES_CHANNEL]), Context::createDefaultContext())
            ->get(Defaults::SALES_CHANNEL);

        $paymentMethod = (new PaymentMethodEntity())->assign(
            [
                'id' => $this->getValidPaymentMethodId(),
                'handlerIdentifier' => SyncTestPaymentHandler::class,
                'name' => 'Generated Payment',
                'active' => true,
            ]
        );

        $context = Generator::createSalesChannelContext(
            null,
            null,
            null,
            $salesChannel,
            null,
            null,
            null,
            null,
            null,
            $paymentMethod
        );
        $errors = new ErrorCollection();

        $this->validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors);
    }
}
