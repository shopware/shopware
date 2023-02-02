<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Payment;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\CapturePreparedPaymentException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\CaptureResponse;
use Shopware\Core\Framework\App\Payment\Response\ValidateResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
class AppPreparedPaymentHandlerTest extends AbstractAppPaymentHandlerTest
{
    public function testValidate(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = ValidateResponse::create(null, ['preOrderPayment' => ['test' => 'response']]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $returnValue = $this->preparedPaymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
        static::assertInstanceOf(ArrayStruct::class, $returnValue);
        static::assertSame(['test' => 'response'], $returnValue->all());

        /** @var Request $request */
        $request = $this->getLastRequest();
        $body = $request->getBody()->getContents();

        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(hash_hmac('sha256', $body, $this->app->getAppSecret()), $request->getHeaderLine('shopware-shop-signature'));
        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = json_decode($body, true);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
        ], $content['source']);
        static::assertArrayHasKey('cart', $content);
        static::assertIsArray($content['cart']);
        static::assertArrayHasKey('requestData', $content);
        static::assertIsArray($content['requestData']);
        static::assertArrayHasKey('salesChannelContext', $content);
        static::assertIsArray($content['salesChannelContext']);
        static::assertArrayHasKey('customer', $content['salesChannelContext']);
        static::assertIsArray($content['salesChannelContext']['customer']);
        // sensitive data is removed
        static::assertArrayNotHasKey('password', $content['salesChannelContext']['customer']);
        static::assertCount(4, $content);
    }

    public function testValidateWithoutUrl(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('sync');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $this->preparedPaymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);

        /** @var Request $request */
        static::assertSame(0, $this->getRequestCount());
    }

    public function testValidateWithErrorMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = (new ValidateResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(ValidatePreparedPaymentException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->preparedPaymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testValidateWithUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = new ValidateResponse();
        $this->appendNewResponse(new Response(200, [], json_encode($response)));

        $this->expectException(ValidatePreparedPaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->preparedPaymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testValidateWithWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = new ValidateResponse();
        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], json_encode($response)));

        $this->expectException(ValidatePreparedPaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->preparedPaymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testValidateWithErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $this->appendNewResponse(new Response(500));

        $this->expectException(ValidatePreparedPaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->preparedPaymentService->handlePreOrderPayment($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testCapture(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = new CaptureResponse();
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct(['test' => 'test']));

        /** @var Request $request */
        $request = $this->getLastRequest();
        $body = $request->getBody()->getContents();

        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(hash_hmac('sha256', $body, $this->app->getAppSecret()), $request->getHeaderLine('shopware-shop-signature'));
        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = json_decode($body, true);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
        ], $content['source']);
        static::assertArrayHasKey('order', $content);
        static::assertIsArray($content['order']);
        static::assertArrayHasKey('orderCustomer', $content['order']);
        static::assertIsArray($content['order']['orderCustomer']);
        static::assertArrayHasKey('customer', $content['order']['orderCustomer']);
        static::assertIsArray($content['order']['orderCustomer']['customer']);
        // sensitive data is removed
        static::assertArrayNotHasKey('password', $content['order']['orderCustomer']['customer']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertArrayHasKey('preOrderPayment', $content);
        static::assertIsArray($content['preOrderPayment']);
        static::assertArrayHasKey('test', $content['preOrderPayment']);
        static::assertSame('test', $content['preOrderPayment']['test']);
        static::assertCount(4, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PAID, $transactionId);
    }

    public function testCaptureWithoutUrl(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('sync');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());

        /** @var Request $request */
        static::assertSame(0, $this->getRequestCount());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);
    }

    public function testCaptureOtherState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = (new CaptureResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_PAID_PARTIALLY,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PARTIALLY_PAID, $transactionId);
    }

    public function testCaptureFailedState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = (new CaptureResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_FAIL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(CapturePreparedPaymentException::class);
        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureFailedStateWithMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = (new CaptureResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_FAIL,
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(CapturePreparedPaymentException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureNoStateButMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = (new CaptureResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(CapturePreparedPaymentException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureWithUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = new CaptureResponse();
        $this->appendNewResponse(new Response(200, [], json_encode($response)));

        $this->expectException(CapturePreparedPaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureWithWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = new CaptureResponse();
        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], json_encode($response)));

        $this->expectException(CapturePreparedPaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureWithErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $this->appendNewResponse(new Response(500));

        $this->expectException(CapturePreparedPaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    private function loadOrder(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('orderCustomer.salutation')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('transactions.paymentMethod.appPaymentMethod.app')
            ->addAssociation('lineItems.cover')
            ->addAssociation('currency')
            ->addAssociation('addresses.country')
            ->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();
        static::assertNotNull($order);

        return $order;
    }
}
