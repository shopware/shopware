<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentProcessor::class)]
class PaymentProcessorTest extends TestCase
{
    private PaymentProcessor $processor;

    /**
     * @var StaticEntityRepository<OrderTransactionCollection>
     */
    private StaticEntityRepository $orderTransactionRepository;

    private PaymentHandlerRegistry&MockObject $paymentHandlerRegistry;

    private AbstractPaymentTransactionStructFactory&MockObject $structFactory;

    private RouterInterface&MockObject $router;

    private TokenFactoryInterfaceV2&MockObject $tokenGenerator;

    protected function setUp(): void
    {
        $this->processor = new PaymentProcessor(
            $this->createMock(PaymentTransactionChainProcessor::class),
            $this->tokenGenerator = $this->createMock(TokenFactoryInterfaceV2::class),
            $this->paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class),
            $this->orderTransactionRepository = new StaticEntityRepository([]),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(LoggerInterface::class),
            $this->structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class),
            $this->createMock(InitialStateIdLoader::class),
            $this->router = $this->createMock(RouterInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(PaymentService::class),
        );
    }

    public function testPay(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::createSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $this->structFactory
            ->expects(static::once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext(), 'return-url')
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects(static::once())
            ->method('pay')
            ->with($request, $struct, $salesChannelContext->getContext(), null)
            ->willReturn(null);

        $this->paymentHandlerRegistry->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $this->tokenGenerator
            ->expects(static::once())
            ->method('generateToken')
            ->willReturn('token');

        $this->router
            ->expects(static::once())
            ->method('generate')
            ->with('payment.finalize.transaction', ['_sw_payment_token' => 'token'])
            ->willReturn('return-url');

        $response = $this->processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertNull($response);
    }

    public function testFinalize(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::createSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $this->structFactory
            ->expects(static::once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext())
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects(static::once())
            ->method('finalize')
            ->with($request, $struct, $salesChannelContext->getContext());

        $this->paymentHandlerRegistry->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            expires: \PHP_INT_MAX,
        );

        $response = $this->processor->finalize(
            $tokenStruct,
            $request,
            $salesChannelContext,
        );

        static::assertSame($tokenStruct, $response);
    }

    public function testValidate(): void
    {
        $requestDataBag = new RequestDataBag();
        $salesChannelContext = Generator::createSalesChannelContext();
        $cart = new Cart(Uuid::randomHex());
        $cart->getTransactions()->add(new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id'));

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects(static::once())
            ->method('validate')
            ->with($cart, $requestDataBag, $salesChannelContext)
            ->willReturn(new ArrayStruct(['validationData']));

        $this->paymentHandlerRegistry->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with($salesChannelContext->getPaymentMethod()->getId())
            ->willReturn($handler);

        $struct = $this->processor->validate(
            $cart,
            $requestDataBag,
            $salesChannelContext,
        );

        static::assertContains('validationData', $struct?->jsonSerialize() ?? []);
        static::assertSame($struct, $cart->getTransactions()->first()?->getValidationStruct());
    }
}
