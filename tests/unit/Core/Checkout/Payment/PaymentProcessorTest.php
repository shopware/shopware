<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentProcessor::class)]
class PaymentProcessorTest extends TestCase
{
    private const INITIAL_STATE_ID = 'initial-state-id';

    private PaymentProcessor $processor;

    /**
     * @var StaticEntityRepository<OrderTransactionCollection>
     */
    private StaticEntityRepository $orderTransactionRepository;

    private PaymentHandlerRegistry&Stub $paymentHandlerRegistry;

    private AbstractPaymentTransactionStructFactory&Stub $structFactory;

    private RouterInterface&Stub $router;

    private TokenFactoryInterfaceV2&Stub $tokenFactory;

    private OrderTransactionStateHandler&Stub $stateHandler;

    private PaymentTokenGenerator&Stub $tokenGenerator;

    private PaymentTokenLifecycle&Stub $tokenLifecycle;

    protected function setUp(): void
    {
        $this->tokenFactory = static::createStub(TokenFactoryInterfaceV2::class);
        $this->tokenGenerator = static::createStub(PaymentTokenGenerator::class);
        $this->tokenLifecycle = static::createStub(PaymentTokenLifecycle::class);
        $this->paymentHandlerRegistry = static::createStub(PaymentHandlerRegistry::class);
        $this->orderTransactionRepository = new StaticEntityRepository([]);
        $this->stateHandler = static::createStub(OrderTransactionStateHandler::class);
        $this->structFactory = static::createStub(AbstractPaymentTransactionStructFactory::class);
        $this->router = static::createStub(RouterInterface::class);

        $this->processor = $this->createProcessor();
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPayOldStruct(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext(), 'return-url')
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('pay')
            ->with($request, $struct, $salesChannelContext->getContext(), null)
            ->willReturn(null);

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('token');

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('payment.finalize.transaction', ['_sw_payment_token' => 'token'])
            ->willReturn('return-url');

        $tokenFactory
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token');

        $processor = $this->createProcessor(
            tokenFactory: $tokenFactory,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            structFactory: $structFactory,
            router: $router,
        );

        $response = $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertNull($response);
    }

    public function testPay(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext(), 'return-url')
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('pay')
            ->with($request, $struct, $salesChannelContext->getContext(), null)
            ->willReturn(null);

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('encode')
            ->with(static::callback(static function (PaymentToken $token) use ($salesChannelContext): bool {
                $token->jti = 'token-id';
                $token->exp = new \DateTimeImmutable();
                static::assertSame('order-transaction-id', $token->transactionId);
                static::assertSame('payment-method-id', $token->paymentMethodId);
                static::assertSame($salesChannelContext->getSalesChannelId(), $token->salesChannelId);

                return true;
            }))
            ->willReturn('token');

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('addToken')
            ->with('token-id');

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('payment.finalize.transaction', ['_sw_payment_token' => 'token'])
            ->willReturn('return-url');

        $tokenLifecycle
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token-id');

        $processor = $this->createProcessor(
            tokenGenerator: $tokenGenerator,
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            structFactory: $structFactory,
            router: $router,
        );

        $response = $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertNull($response);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPayWithRedirectResponseOldStruct(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext(), 'return-url')
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('pay')
            ->with($request, $struct, $salesChannelContext->getContext(), null)
            ->willReturn(new RedirectResponse('redirect-url'));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('token');

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('payment.finalize.transaction', ['_sw_payment_token' => 'token'])
            ->willReturn('return-url');

        $tokenFactory
            ->expects($this->never())
            ->method('invalidateToken');

        $processor = $this->createProcessor(
            tokenFactory: $tokenFactory,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            structFactory: $structFactory,
            router: $router,
        );

        $response = $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('redirect-url', $response->getTargetUrl());
    }

    public function testPayWithRedirectResponse(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext(), 'return-url')
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('pay')
            ->with($request, $struct, $salesChannelContext->getContext(), null)
            ->willReturn(new RedirectResponse('redirect-url'));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('encode')
            ->with(static::callback(static function (PaymentToken $token) use ($salesChannelContext): bool {
                $token->jti = 'token-id';
                $token->exp = new \DateTimeImmutable();
                static::assertSame('order-transaction-id', $token->transactionId);
                static::assertSame('payment-method-id', $token->paymentMethodId);
                static::assertSame($salesChannelContext->getSalesChannelId(), $token->salesChannelId);

                return true;
            }))
            ->willReturn('token');

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('addToken')
            ->with('token-id');

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('payment.finalize.transaction', ['_sw_payment_token' => 'token'])
            ->willReturn('return-url');

        $tokenLifecycle
            ->expects($this->never())
            ->method('invalidateToken');

        $processor = $this->createProcessor(
            tokenGenerator: $tokenGenerator,
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            structFactory: $structFactory,
            router: $router,
        );

        $response = $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('redirect-url', $response->getTargetUrl());
    }

    public function testPayWithoutTransaction(): void
    {
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([]));
        $this->orderTransactionRepository->addSearch(['order-transaction-id']);

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->expectExceptionObject(PaymentException::invalidOrder('order-id'));
        $this->processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );
    }

    public function testPayWithInvalidOrder(): void
    {
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([]));
        $this->orderTransactionRepository->addSearch([]);

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->expectExceptionObject(PaymentException::invalidOrder('order-id'));
        $this->processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPayWithoutHandlerOldStruct(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn(null);

        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('token');

        $tokenFactory
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token');

        $processor = $this->createProcessor(
            tokenFactory: $tokenFactory,
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $response = $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertSame('error-url?error-code=' . PaymentException::PAYMENT_UNKNOWN_PAYMENT_METHOD, $response?->getTargetUrl());
    }

    public function testPayWithoutHandler(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn(null);

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('encode')
            ->with(static::callback(static function (PaymentToken $token) use ($salesChannelContext): bool {
                $token->jti = 'token-id';
                $token->exp = new \DateTimeImmutable();
                static::assertSame('order-transaction-id', $token->transactionId);
                static::assertSame('payment-method-id', $token->paymentMethodId);
                static::assertSame($salesChannelContext->getSalesChannelId(), $token->salesChannelId);

                return true;
            }))
            ->willReturn('token');

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('addToken')
            ->with('token-id');

        $tokenLifecycle
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token-id');

        $processor = $this->createProcessor(
            tokenGenerator: $tokenGenerator,
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $response = $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
            'error-url',
        );

        static::assertSame('error-url?error-code=' . PaymentException::PAYMENT_UNKNOWN_PAYMENT_METHOD, $response?->getTargetUrl());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPayWithoutHandlerAndErrorUrlOldStruct(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn(null);

        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('generateToken')
            ->willReturn('token');

        $tokenFactory
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token');

        $processor = $this->createProcessor(
            tokenFactory: $tokenFactory,
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('payment-method-id'));
        $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
        );
    }

    public function testPayWithoutHandlerAndErrorUrl(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn(null);

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('encode')
            ->with(static::callback(static function (PaymentToken $token) use ($salesChannelContext): bool {
                $token->jti = 'token-id';
                $token->exp = new \DateTimeImmutable();
                static::assertSame('order-transaction-id', $token->transactionId);
                static::assertSame('payment-method-id', $token->paymentMethodId);
                static::assertSame($salesChannelContext->getSalesChannelId(), $token->salesChannelId);

                return true;
            }))
            ->willReturn('token');

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('addToken')
            ->with('token-id');

        $tokenLifecycle
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token-id');

        $processor = $this->createProcessor(
            tokenGenerator: $tokenGenerator,
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('payment-method-id'));
        $processor->pay(
            'order-id',
            $request,
            $salesChannelContext,
            'finish-url',
        );
    }

    public function testFinalize(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext())
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('finalize')
            ->with($request, $struct, $salesChannelContext->getContext());

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $token = new PaymentToken();
        $token->paymentMethodId = 'payment-method-id';
        $token->transactionId = 'order-transaction-id';
        $token->jti = 'token-id';

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token-id');

        $fakeTokenStruct = null;
        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });
        static::assertInstanceOf(TokenStruct::class, $fakeTokenStruct);

        $processor = $this->createProcessor(
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            structFactory: $structFactory,
        );

        $processor->finalize(
            $fakeTokenStruct,
            $request,
            $salesChannelContext,
            $token,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, as properties are required with new struct
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFinalizeWithInvalidToken(): void
    {
        $this->expectExceptionObject(PaymentException::invalidToken(''));

        $this->processor->finalize(
            new TokenStruct(),
            new Request(),
            Generator::generateSalesChannelContext(),
        );
    }

    public function testFinalizeWithoutTransaction(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn(null);

        $token = new PaymentToken();
        $token->paymentMethodId = 'payment-method-id';
        $token->transactionId = 'order-transaction-id';

        $fakeTokenStruct = null;
        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });
        static::assertInstanceOf(TokenStruct::class, $fakeTokenStruct);

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('payment-method-id'));
        $processor->finalize(
            $fakeTokenStruct,
            $request,
            $salesChannelContext,
            $token,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFinalizeUserCancelledOldStruct(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext())
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('finalize')
            ->with($request, $struct, $salesChannelContext->getContext())
            ->willThrowException(PaymentException::customerCanceled('order-transaction-id', 'cancelled'));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            expires: \PHP_INT_MAX,
        );

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('cancel')
            ->with('order-transaction-id', $salesChannelContext->getContext());

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
            stateHandler: $stateHandler,
            structFactory: $structFactory,
        );

        $response = $processor->finalize(
            $tokenStruct,
            $request,
            $salesChannelContext,
        );

        static::assertSame($tokenStruct, $response);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFinalizeUserCancelled(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext())
            ->willReturn($struct);

        $exception = PaymentException::customerCanceled('order-transaction-id', 'cancelled');
        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('finalize')
            ->with($request, $struct, $salesChannelContext->getContext())
            ->willThrowException($exception);

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $token = new PaymentToken();
        $token->paymentMethodId = 'payment-method-id';
        $token->transactionId = 'order-transaction-id';
        $token->jti = 'token-id';

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token-id');

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('cancel')
            ->with('order-transaction-id', $salesChannelContext->getContext());

        $processor = $this->createProcessor(
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            stateHandler: $stateHandler,
            structFactory: $structFactory,
        );

        $this->expectExceptionObject($exception);
        $processor->finalize(
            new TokenStruct(),
            $request,
            $salesChannelContext,
            $token,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFinalizeFailedOldStruct(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext())
            ->willReturn($struct);

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('finalize')
            ->with($request, $struct, $salesChannelContext->getContext())
            ->willThrowException(PaymentException::asyncFinalizeInterrupted('order-transaction-id', 'failed'));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            expires: \PHP_INT_MAX,
        );

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('fail')
            ->with('order-transaction-id', $salesChannelContext->getContext());

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
            stateHandler: $stateHandler,
            structFactory: $structFactory,
        );

        $response = $processor->finalize(
            $tokenStruct,
            $request,
            $salesChannelContext,
        );

        static::assertSame($tokenStruct, $response);
    }

    public function testFinalizeFailed(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $struct = new PaymentTransactionStruct('order-transaction-id', 'return-url');
        $structFactory = $this->createMock(AbstractPaymentTransactionStructFactory::class);
        $structFactory
            ->expects($this->once())
            ->method('build')
            ->with('order-transaction-id', $salesChannelContext->getContext())
            ->willReturn($struct);

        $exception = PaymentException::asyncFinalizeInterrupted('order-transaction-id', 'failed');
        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('finalize')
            ->with($request, $struct, $salesChannelContext->getContext())
            ->willThrowException($exception);

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('payment-method-id')
            ->willReturn($handler);

        $token = new PaymentToken();
        $token->paymentMethodId = 'payment-method-id';
        $token->transactionId = 'order-transaction-id';
        $token->jti = 'token-id';

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('token-id');

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('fail')
            ->with('order-transaction-id', $salesChannelContext->getContext());

        $fakeTokenStruct = null;
        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });
        static::assertInstanceOf(TokenStruct::class, $fakeTokenStruct);

        $processor = $this->createProcessor(
            tokenLifecycle: $tokenLifecycle,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            stateHandler: $stateHandler,
            structFactory: $structFactory,
        );

        $this->expectExceptionObject($exception);
        $processor->finalize(
            $fakeTokenStruct,
            $request,
            $salesChannelContext,
            $token,
        );
    }

    public function testPayKeepsTheErrorRedirectWhenTheTransactionCanNoLongerBeFailed(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('payment-method-id');
        $orderTransaction->setStateId(self::INITIAL_STATE_ID);
        $this->orderTransactionRepository->addSearch(new OrderTransactionCollection([$orderTransaction]));

        $paymentHandlerRegistry = static::createStub(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->method('getPaymentMethodHandler')->willReturn(null);

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler->expects($this->once())
            ->method('fail')
            ->willThrowException(new IllegalTransitionException('paid-state-id', 'fail', ['refund']));

        $tokenGenerator = static::createStub(PaymentTokenGenerator::class);
        $tokenGenerator->method('encode')
            ->willReturnCallback(static function (PaymentToken $token): string {
                $token->jti = 'token-id';
                $token->exp = new \DateTimeImmutable();

                return 'token';
            });

        $processor = $this->createProcessor(
            tokenGenerator: $tokenGenerator,
            paymentHandlerRegistry: $paymentHandlerRegistry,
            stateHandler: $stateHandler,
        );

        $response = $processor->pay(
            'order-id',
            new Request(),
            Generator::generateSalesChannelContext(),
            'finish-url',
            'error-url',
        );

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(
            'error-url?error-code=' . PaymentException::PAYMENT_UNKNOWN_PAYMENT_METHOD,
            $response->getTargetUrl()
        );
    }

    public function testFinalizeReportsThePaymentErrorWhenTheTransactionCanNoLongerBeFailed(): void
    {
        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler->expects($this->once())
            ->method('finalize')
            ->willThrowException(PaymentException::asyncFinalizeInterrupted('order-transaction-id', 'handler said no'));

        $paymentHandlerRegistry = static::createStub(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->method('getPaymentMethodHandler')->willReturn($handler);

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler->expects($this->once())
            ->method('fail')
            ->willThrowException(new IllegalTransitionException('paid-state-id', 'fail', ['refund']));

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
            stateHandler: $stateHandler,
        );

        $token = new PaymentToken();
        $token->transactionId = 'order-transaction-id';
        $token->paymentMethodId = 'payment-method-id';

        $fakeTokenStruct = null;
        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });
        static::assertInstanceOf(TokenStruct::class, $fakeTokenStruct);

        $this->expectExceptionObject(
            PaymentException::asyncFinalizeInterrupted('order-transaction-id', 'handler said no')
        );

        $processor->finalize($fakeTokenStruct, new Request(), Generator::generateSalesChannelContext(), $token);
    }

    public function testValidate(): void
    {
        $requestDataBag = new RequestDataBag();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $cart = new Cart(Uuid::randomHex());
        $cart->getTransactions()->add(new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id'));

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('validate')
            ->with($cart, $requestDataBag, $salesChannelContext)
            ->willReturn(new ArrayStruct(['validationData']));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with($salesChannelContext->getPaymentMethod()->getId())
            ->willReturn($handler);

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $struct = $processor->validate(
            $cart,
            $requestDataBag,
            $salesChannelContext,
        );

        static::assertContains('validationData', $struct?->jsonSerialize() ?? []);
        static::assertSame($struct, $cart->getTransactions()->first()?->getValidationStruct());
    }

    public function testValidateWithoutHandler(): void
    {
        $requestDataBag = new RequestDataBag();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getPaymentMethod()->setId('payment-method-id');
        $cart = new Cart(Uuid::randomHex());
        $cart->getTransactions()->add(new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id'));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with($salesChannelContext->getPaymentMethod()->getId())
            ->willReturn(null);

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('payment-method-id'));
        $processor->validate(
            $cart,
            $requestDataBag,
            $salesChannelContext,
        );
    }

    public function testValidateFails(): void
    {
        $requestDataBag = new RequestDataBag();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getPaymentMethod()->setId('payment-method-id');
        $cart = new Cart(Uuid::randomHex());
        $cart->getTransactions()->add(new Transaction(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), 'payment-method-id'));

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('validate')
            ->with($cart, $requestDataBag, $salesChannelContext)
            ->willThrowException(PaymentException::validatePreparedPaymentInterrupted('failed'));

        $paymentHandlerRegistry = $this->createMock(PaymentHandlerRegistry::class);
        $paymentHandlerRegistry->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with($salesChannelContext->getPaymentMethod()->getId())
            ->willReturn($handler);

        $processor = $this->createProcessor(
            paymentHandlerRegistry: $paymentHandlerRegistry,
        );

        $this->expectExceptionObject(PaymentException::validatePreparedPaymentInterrupted('failed'));
        $processor->validate(
            $cart,
            $requestDataBag,
            $salesChannelContext,
        );
    }

    private function createProcessor(
        ?TokenFactoryInterfaceV2 $tokenFactory = null,
        ?PaymentTokenGenerator $tokenGenerator = null,
        ?PaymentTokenLifecycle $tokenLifecycle = null,
        ?PaymentHandlerRegistry $paymentHandlerRegistry = null,
        ?OrderTransactionStateHandler $stateHandler = null,
        ?AbstractPaymentTransactionStructFactory $structFactory = null,
        ?RouterInterface $router = null,
    ): PaymentProcessor {
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn(self::INITIAL_STATE_ID);

        return new PaymentProcessor(
            $tokenFactory ?? $this->tokenFactory,
            $tokenGenerator ?? $this->tokenGenerator,
            $tokenLifecycle ?? $this->tokenLifecycle,
            $paymentHandlerRegistry ?? $this->paymentHandlerRegistry,
            $this->orderTransactionRepository,
            $stateHandler ?? $this->stateHandler,
            static::createStub(LoggerInterface::class),
            $structFactory ?? $this->structFactory,
            $initialStateIdLoader,
            $router ?? $this->router,
            static::createStub(SystemConfigService::class),
        );
    }
}
