<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
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
    private TokenFactoryInterfaceV2&MockObject $tokenFactory;

    /**
     * @var StaticEntityRepository<OrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    private OrderConverter&MockObject $orderConverter;

    private PaymentProcessor&MockObject $paymentProcessor;

    private PaymentController $controller;

    protected function setUp(): void
    {
        $this->controller = new PaymentController(
            $this->paymentProcessor = $this->createMock(PaymentProcessor::class),
            $this->orderConverter = $this->createMock(OrderConverter::class),
            $this->tokenFactory = $this->createMock(TokenFactoryInterfaceV2::class),
            $this->orderRepository = new StaticEntityRepository([]),
        );
    }

    public function testFinalizeTransaction(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            finishUrl: 'finish-url',
            expires: \PHP_INT_MAX,
        );
        $this->tokenFactory
            ->expects(static::once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $salesChannelContext = Generator::createSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $this->orderConverter
            ->expects(static::once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        $this->paymentProcessor
            ->expects(static::once())
            ->method('finalize')
            ->with($tokenStruct, $request, $salesChannelContext)
            ->willReturn($tokenStruct);

        $response = $this->controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('finish-url', $response->getTargetUrl());
    }

    public function testFinalizeTransactionReturnsShopwareException(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            expires: \PHP_INT_MAX,
        );
        $this->tokenFactory
            ->expects(static::once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $salesChannelContext = Generator::createSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $this->orderConverter
            ->expects(static::once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        $this->paymentProcessor
            ->expects(static::once())
            ->method('finalize')
            ->with($tokenStruct, $request, $salesChannelContext)
            ->willReturn($tokenStruct);
        $tokenStruct->setException(PaymentException::customerCanceled('order-transaction-id', 'nothing'));

        $response = $this->controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url?error-code=CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT', $response->getTargetUrl());
    }

    public function testFinalizeTransactionReturnsOtherException(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            expires: \PHP_INT_MAX,
        );
        $this->tokenFactory
            ->expects(static::once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $salesChannelContext = Generator::createSalesChannelContext();
        $order = new OrderEntity();
        $order->setId('order-id');
        $this->orderRepository->addSearch(new OrderCollection([$order]));
        $this->orderConverter
            ->expects(static::once())
            ->method('assembleSalesChannelContext')
            ->with($order, Context::createDefaultContext())
            ->willReturn($salesChannelContext);

        $this->paymentProcessor
            ->expects(static::once())
            ->method('finalize')
            ->with($tokenStruct, $request, $salesChannelContext)
            ->willReturn($tokenStruct);
        $tokenStruct->setException(new \RuntimeException('nothing'));

        $response = $this->controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url', $response->getTargetUrl());
    }

    public function testFinalizeTransactionTokenWithMissingTransactionId(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            errorUrl: 'error-url',
            token: 'test-token',
            expires: \PHP_INT_MAX,
        );
        $this->tokenFactory
            ->expects(static::once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $this->orderConverter
            ->expects(static::never())
            ->method('assembleSalesChannelContext');

        $this->paymentProcessor
            ->expects(static::never())
            ->method('finalize');

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The provided token test-token is invalid and the payment could not be processed.');
        $this->controller->finalizeTransaction($request);
    }

    public function testFinalizeTransactionTokenWithInvalidTransactionId(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            token: 'test-token',
            expires: \PHP_INT_MAX,
        );
        $this->tokenFactory
            ->expects(static::once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $this->orderRepository->addSearch(new OrderCollection([]));

        $this->orderConverter
            ->expects(static::never())
            ->method('assembleSalesChannelContext');

        $this->paymentProcessor
            ->expects(static::never())
            ->method('finalize');

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The provided token test-token is invalid and the payment could not be processed.');
        $this->controller->finalizeTransaction($request);
    }

    public function testFinalizeTransactionExpiredToken(): void
    {
        $request = new Request([], ['_sw_payment_token' => 'test-token']);

        $tokenStruct = new TokenStruct(
            paymentMethodId: 'payment-method-id',
            transactionId: 'order-transaction-id',
            errorUrl: 'error-url',
            token: 'test-token',
            expires: 0,
        );
        $this->tokenFactory
            ->expects(static::once())
            ->method('parseToken')
            ->with('test-token')
            ->willReturn($tokenStruct);

        $this->tokenFactory
            ->expects(static::once())
            ->method('invalidateToken')
            ->with('test-token');

        $this->paymentProcessor
            ->expects(static::never())
            ->method('finalize');

        $response = $this->controller->finalizeTransaction($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('error-url?error-code=CHECKOUT__PAYMENT_TOKEN_EXPIRED', $response->getTargetUrl());
    }

    public function testFinalizeTransactionNoToken(): void
    {
        $this->tokenFactory
            ->expects(static::never())
            ->method('parseToken');

        $this->paymentProcessor
            ->expects(static::never())
            ->method('finalize');

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Parameter "_sw_payment_token" is missing.');
        $this->controller->finalizeTransaction(new Request());
    }
}
