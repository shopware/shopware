<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\CaptureResponse;
use Shopware\Core\Framework\App\Payment\Response\ValidateResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
class AppPreparedPaymentHandlerTest extends AbstractAppPaymentHandlerTestCase
{
    public function testValidate(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = ValidateResponse::create(['preOrderPayment' => ['test' => 'response']]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $returnValue = $this->paymentProcessor->validate($cart, new RequestDataBag(), $salesChannelContext);
        static::assertInstanceOf(ArrayStruct::class, $returnValue);
        static::assertSame(['test' => 'response'], $returnValue->all());

        $request = $this->getLastRequest();
        static::assertNotNull($request);
        $body = $request->getBody()->getContents();

        $appSecret = $this->app->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(hash_hmac('sha256', $body, $appSecret), $request->getHeaderLine('shopware-shop-signature'));
        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
            'inAppPurchases' => [],
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

        $this->paymentProcessor->validate($cart, new RequestDataBag(), $salesChannelContext);

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

        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches(\sprintf('/%s/', self::ERROR_MESSAGE));
        $this->paymentProcessor->validate($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testValidateWithUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = new ValidateResponse();
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        $this->paymentProcessor->validate($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testValidateWithWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $response = new ValidateResponse();
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        $this->paymentProcessor->validate($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testValidateWithErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $cart = Generator::createCart();
        $customerId = $this->createCustomer();
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId, $customerId);

        $this->appendNewResponse(new Response(500));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        $this->paymentProcessor->validate($cart, new RequestDataBag(), $salesChannelContext);
    }

    public function testCapture(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = new CaptureResponse();
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct(['test' => 'test']));

        $request = $this->getLastRequest();
        static::assertNotNull($request);
        $body = $request->getBody()->getContents();

        $appSecret = $this->app->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(\hash_hmac('sha256', $body, $appSecret), $request->getHeaderLine('shopware-shop-signature'));
        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
            'inAppPurchases' => [],
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
        static::assertArrayHasKey('recurring', $content);
        static::assertNull($content['recurring']);
        static::assertCount(5, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PAID, $transactionId);
    }

    public function testCaptureWithoutUrl(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('sync');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());

        static::assertSame(0, $this->getRequestCount());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);
    }

    public function testCaptureOtherState(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

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
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = (new CaptureResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_FAIL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . 'Payment was reported as failed.');

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureFailedStateWithMessage(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

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

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . self::ERROR_MESSAGE);

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureNoStateButMessage(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = (new CaptureResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . self::ERROR_MESSAGE);

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureWithUnsignedResponse(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = new CaptureResponse();
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureWithWronglySignedResponse(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $response = new CaptureResponse();
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');

        $this->preparedPaymentService->handlePostOrderPayment($order, new RequestDataBag(), $salesChannelContext, new ArrayStruct());
    }

    public function testCaptureWithErrorResponse(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethodId = $this->getPaymentMethodId('prepared');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);
        $order = $this->loadOrder($orderId, $salesChannelContext);

        $this->appendNewResponse(new Response(500));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');

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

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();
        static::assertNotNull($order);

        return $order;
    }
}
