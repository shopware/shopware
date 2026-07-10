<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentController::class)]
class PaymentControllerTest extends TestCase
{
    private TokenFactoryInterfaceV2&Stub $tokenFactory;

    /**
     * @var StaticEntityRepository<OrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    private OrderConverter&Stub $orderConverter;

    private PaymentProcessor&MockObject $paymentProcessor;

    private PaymentTokenGenerator&Stub $tokenGenerator;

    private PaymentTokenLifecycle&Stub $tokenLifecycle;

    protected function setUp(): void
    {
        $this->orderRepository = new StaticEntityRepository([]);
        $this->paymentProcessor = $this->createMock(PaymentProcessor::class);
        $this->orderConverter = static::createStub(OrderConverter::class);
        $this->tokenFactory = static::createStub(TokenFactoryInterfaceV2::class);
        $this->tokenGenerator = static::createStub(PaymentTokenGenerator::class);
        $this->tokenLifecycle = static::createStub(PaymentTokenLifecycle::class);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0', 'REPEATED_PAYMENT_FINALIZE'])]
    public function testFinalizeTransactionOldStruct(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            finishUrl: 'finish-url',
            expires: \PHP_INT_MAX,
        );
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        $this->paymentProcessor
            ->expects($this->once())
            ->method('finalize')
            ->with($tokenStruct, $request, $salesChannelContext)
            ->willReturn($tokenStruct);

        $controller = $this->createController(orderConverter: $orderConverter, tokenFactory: $tokenFactory);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('finish-url', $response->getTargetUrl());
    }

    public function testFinalizeTransaction(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $paymentToken = new PaymentToken();
        $paymentToken->paymentMethodId = 'payment-method-id';
        $paymentToken->transactionId = 'order-transaction-id';
        $paymentToken->finishUrl = 'finish-url';
        $paymentToken->jti = 'token-id';

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with('test-token')
            ->willReturn($paymentToken);

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('isConsumable')
            ->with('token-id')
            ->willReturn(true);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });

        $this->paymentProcessor
            ->expects($this->once())
            ->method('finalize')
            ->with($fakeTokenStruct, $request, $salesChannelContext, $paymentToken)
            ->willReturn($fakeTokenStruct);

        $controller = $this->createController(orderConverter: $orderConverter, tokenGenerator: $tokenGenerator, tokenLifecycle: $tokenLifecycle);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('finish-url', $response->getTargetUrl());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0', 'REPEATED_PAYMENT_FINALIZE'])]
    public function testFinalizeTransactionReturnsShopwareExceptionOldStruct(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            expires: \PHP_INT_MAX,
        );
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        $this->paymentProcessor
            ->expects($this->once())
            ->method('finalize')
            ->with($tokenStruct, $request, $salesChannelContext)
            ->willReturn($tokenStruct);
        $tokenStruct->setException(PaymentException::customerCanceled('order-transaction-id', 'nothing'));

        $controller = $this->createController(orderConverter: $orderConverter, tokenFactory: $tokenFactory);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url?error-code=CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT', $response->getTargetUrl());
    }

    public function testFinalizeTransactionReturnsShopwareException(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $paymentToken = new PaymentToken();
        $paymentToken->paymentMethodId = 'payment-method-id';
        $paymentToken->transactionId = 'order-transaction-id';
        $paymentToken->errorUrl = 'error-url';
        $paymentToken->jti = 'token-id';

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with('test-token')
            ->willReturn($paymentToken);

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('isConsumable')
            ->with('token-id')
            ->willReturn(true);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });

        $this->paymentProcessor
            ->expects($this->once())
            ->method('finalize')
            ->with($fakeTokenStruct, $request, $salesChannelContext, $paymentToken)
            ->willThrowException(PaymentException::customerCanceled('order-transaction-id', 'nothing'));

        $controller = $this->createController(orderConverter: $orderConverter, tokenGenerator: $tokenGenerator, tokenLifecycle: $tokenLifecycle);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url?error-code=CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT', $response->getTargetUrl());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0', 'REPEATED_PAYMENT_FINALIZE'])]
    public function testFinalizeTransactionReturnsOtherExceptionOldStruct(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            expires: \PHP_INT_MAX,
        );
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        $this->paymentProcessor
            ->expects($this->once())
            ->method('finalize')
            ->with($tokenStruct, $request, $salesChannelContext)
            ->willReturn($tokenStruct);
        $tokenStruct->setException(new \RuntimeException('nothing'));

        $controller = $this->createController(orderConverter: $orderConverter, tokenFactory: $tokenFactory);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url', $response->getTargetUrl());
    }

    public function testFinalizeTransactionReturnsOtherException(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $paymentToken = new PaymentToken();
        $paymentToken->paymentMethodId = 'payment-method-id';
        $paymentToken->transactionId = 'order-transaction-id';
        $paymentToken->errorUrl = 'error-url';
        $paymentToken->jti = 'token-id';

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with('test-token')
            ->willReturn($paymentToken);

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('isConsumable')
            ->with('token-id')
            ->willReturn(true);

        $salesChannelContext = Generator::generateSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        Feature::silent('v6.8.0.0', static function () use (&$fakeTokenStruct): void {
            $fakeTokenStruct = new TokenStruct();
        });

        $this->paymentProcessor
            ->expects($this->once())
            ->method('finalize')
            ->with($fakeTokenStruct, $request, $salesChannelContext, $paymentToken)
            ->willThrowException(new \RuntimeException('nothing'));

        $controller = $this->createController(orderConverter: $orderConverter, tokenGenerator: $tokenGenerator, tokenLifecycle: $tokenLifecycle);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url', $response->getTargetUrl());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, no replacement as transaction id is non-nullable in new struct
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFinalizeTransactionTokenWithMissingTransactionIdOldStruct(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            errorUrl: 'error-url',
            token: 'test-token',
            expires: \PHP_INT_MAX,
        );
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->never())
            ->method('assembleSalesChannelContext');

        $this->paymentProcessor
            ->expects($this->never())
            ->method('finalize');

        $controller = $this->createController(orderConverter: $orderConverter, tokenFactory: $tokenFactory);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url?error-code=CHECKOUT__INVALID_PAYMENT_TOKEN', $response->getTargetUrl());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0', 'REPEATED_PAYMENT_FINALIZE'])]
    public function testFinalizeTransactionTokenWithInvalidTransactionIdOldStruct(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            token: 'test-token',
            expires: \PHP_INT_MAX,
        );
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $this->orderRepository->addSearch(new OrderCollection([]));

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->never())
            ->method('assembleSalesChannelContext');

        $this->paymentProcessor
            ->expects($this->never())
            ->method('finalize');

        $controller = $this->createController(orderConverter: $orderConverter, tokenFactory: $tokenFactory);
        $this->expectExceptionObject(PaymentException::invalidToken('test-token'));
        $controller->finalizeTransaction($request);
    }

    public function testFinalizeTransactionTokenWithInvalidTransactionId(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $paymentToken = new PaymentToken();
        $paymentToken->paymentMethodId = 'payment-method-id';
        $paymentToken->transactionId = 'order-transaction-id';
        $paymentToken->errorUrl = 'error-url';
        $paymentToken->jti = 'token-id';

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with('test-token')
            ->willReturn($paymentToken);

        $tokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $tokenLifecycle
            ->expects($this->once())
            ->method('isConsumable')
            ->with('token-id')
            ->willReturn(true);

        $this->orderRepository->addSearch(new OrderCollection([]));

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->never())
            ->method('assembleSalesChannelContext');

        $this->paymentProcessor
            ->expects($this->never())
            ->method('finalize');

        $controller = $this->createController(orderConverter: $orderConverter, tokenGenerator: $tokenGenerator, tokenLifecycle: $tokenLifecycle);
        $this->expectExceptionObject(PaymentException::invalidToken('token-id'));
        $controller->finalizeTransaction($request);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, no replacement as expiration is checked by decode
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFinalizeTransactionExpiredTokenOldStruct(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            token: 'test-token',
            expires: 0,
        );
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $tokenFactory
            ->expects($this->once())
            ->method('invalidateToken')
            ->with('test-token');

        $this->paymentProcessor
            ->expects($this->never())
            ->method('finalize');

        $controller = $this->createController(tokenFactory: $tokenFactory);
        $response = $controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url?error-code=CHECKOUT__PAYMENT_TOKEN_EXPIRED', $response->getTargetUrl());
    }

    public function testFinalizeTransactionNoToken(): void
    {
        $tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class);
        $tokenFactory
            ->expects($this->never())
            ->method('parseToken');

        $tokenGenerator = $this->createMock(PaymentTokenGenerator::class);
        $tokenGenerator
            ->expects($this->never())
            ->method('decode');

        $this->paymentProcessor
            ->expects($this->never())
            ->method('finalize');

        $controller = $this->createController(tokenFactory: $tokenFactory, tokenGenerator: $tokenGenerator);
        $this->expectExceptionObject(PaymentException::missingRequestParameter('_sw_payment_token'));
        $controller->finalizeTransaction(new Request());
    }

    private function createController(
        ?OrderConverter $orderConverter = null,
        ?TokenFactoryInterfaceV2 $tokenFactory = null,
        ?PaymentTokenGenerator $tokenGenerator = null,
        ?PaymentTokenLifecycle $tokenLifecycle = null,
    ): PaymentController {
        return new PaymentController(
            $this->paymentProcessor,
            $orderConverter ?? $this->orderConverter,
            $tokenFactory ?? $this->tokenFactory,
            $tokenGenerator ?? $this->tokenGenerator,
            $tokenLifecycle ?? $this->tokenLifecycle,
            $this->orderRepository,
        );
    }
}
