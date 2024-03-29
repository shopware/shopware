<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\SyncPayResponse;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
class AppSyncPaymentHandlerTest extends AbstractAppPaymentHandlerTestCase
{
    public function testPay(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = new SyncPayResponse();
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $data = new RequestDataBag(['foo' => 'bar']);
        $this->paymentService->handlePaymentByOrder($orderId, $data, $salesChannelContext);

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
        static::assertArrayHasKey('recurring', $content);
        static::assertNull($content['recurring']);
        static::assertArrayHasKey('requestData', $content);
        static::assertIsArray($content['requestData']);
        static::assertArrayHasKey('foo', $content['requestData']);
        static::assertSame('bar', $content['requestData']['foo']);
        static::assertCount(5, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);
    }

    public function testPayUntracked(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('sync');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertSame(0, $this->getRequestCount());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);
    }

    public function testPayOtherState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new SyncPayResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_PAID_PARTIALLY,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PARTIALLY_PAID, $transactionId);
    }

    public function testPayFailedState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new SyncPayResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_FAIL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The synchronous payment process was interrupted due to the following error:' . \PHP_EOL . 'Payment was reported as failed');

        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayFailedStateWithMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new SyncPayResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_FAIL,
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayNoStateButMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new SyncPayResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = new SyncPayResponse();
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = new SyncPayResponse();
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('syncTracked');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->appendNewResponse(new Response(500));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }
}
