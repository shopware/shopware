<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\AsyncFinalizeResponse;
use Shopware\Core\Framework\App\Payment\Response\AsyncPayResponse;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
class AppAsyncPaymentHandlerTest extends AbstractAppPaymentHandlerTest
{
    final public const REDIRECT_URL = 'http://payment.app/do/something';

    /**
     * @group quarantined
     * @dataProvider dataProviderFinalize
     */
    public function testPay(string $finalizeFunction): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
        static::assertNotNull($response);

        static::assertEquals(self::REDIRECT_URL, $response->getTargetUrl());
        /** @var Request $request */
        $request = $this->getLastRequest();
        $body = $request->getBody()->getContents();

        $appSecret = $this->app->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(\hash_hmac('sha256', $body, $appSecret), $request->getHeaderLine('shopware-shop-signature'));
        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
        ], $content['source']);
        static::assertArrayHasKey('returnUrl', $content);
        static::assertNotEmpty($content['returnUrl']);
        $token = $this->getToken($content['returnUrl']);
        static::assertNotEmpty($token);
        static::assertArrayHasKey('order', $content);
        static::assertIsArray($content['order']);
        static::assertArrayHasKey('orderCustomer', $content['order']);
        static::assertIsArray($content['order']['orderCustomer']);
        static::assertArrayHasKey('customer', $content['order']['orderCustomer']);
        static::assertIsArray($content['order']['orderCustomer']['customer']);
        static::assertArrayHasKey('requestData', $content);
        static::assertIsArray($content['requestData']);
        // sensitive data is removed
        static::assertArrayNotHasKey('password', $content['order']['orderCustomer']['customer']);
        static::assertNull($content['orderTransaction']['paymentMethod']['appPaymentMethod']['app']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertCount(5, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_UNCONFIRMED, $transactionId);

        $this->$finalizeFunction($token, $transactionId, $paymentMethodId);
    }

    public function testPayOtherState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => StateMachineTransitionActions::ACTION_PAID_PARTIALLY,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PARTIALLY_PAID, $transactionId);
    }

    public function testPayFailedState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => StateMachineTransitionActions::ACTION_FAIL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayFailedStateWithMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => StateMachineTransitionActions::ACTION_FAIL,
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayNoStateButMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessageMatches(sprintf('/%s/', self::ERROR_MESSAGE));
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new AsyncPayResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithInvalidResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->appendNewResponse($this->signResponse(['in' => 'valid']));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessageMatches('/No redirect URL provided by App/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function testPayWithErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->appendNewResponse(new Response(500));

        $this->expectException(AsyncPaymentProcessException::class);
        $this->expectExceptionMessageMatches('/Invalid app response/');
        $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);
    }

    public function addTestFinalizeWithUnsignedResponse(string $token, string $transactionId, string $paymentMethodId): void
    {
        $response = (new AsyncFinalizeResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $return = $this->paymentService->finalizeTransaction($token, new \Symfony\Component\HttpFoundation\Request(), $this->getSalesChannelContext($paymentMethodId));
        static::assertInstanceOf(AsyncPaymentFinalizeException::class, $return->getException());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $transactionId);
    }

    public function addTestFinalizeWithWronglySignedResponse(string $token, string $transactionId, string $paymentMethodId): void
    {
        $response = (new AsyncFinalizeResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $return = $this->paymentService->finalizeTransaction($token, new \Symfony\Component\HttpFoundation\Request(), $this->getSalesChannelContext($paymentMethodId));
        static::assertInstanceOf(AsyncPaymentFinalizeException::class, $return->getException());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $transactionId);
    }

    public function addTestFinalizeWithErrorResponse(string $token, string $transactionId, string $paymentMethodId): void
    {
        $this->appendNewResponse(new Response(500));

        $return = $this->paymentService->finalizeTransaction($token, new \Symfony\Component\HttpFoundation\Request(), $this->getSalesChannelContext($paymentMethodId));
        static::assertInstanceOf(AsyncPaymentFinalizeException::class, $return->getException());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $transactionId);
    }

    public function dataProviderFinalize(): \Generator
    {
        foreach (get_class_methods($this) as $functionName) {
            if (str_starts_with($functionName, 'addTestFinalize')) {
                yield str_replace('addTest', '', $functionName) => [$functionName];
            }
        }
    }

    private function addTestFinalize(string $token, string $transactionId, string $paymentMethodId): void
    {
        $response = (new AsyncFinalizeResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_AUTHORIZE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentService->finalizeTransaction($token, new \Symfony\Component\HttpFoundation\Request(), $this->getSalesChannelContext($paymentMethodId));

        /** @var Request $request */
        $request = $this->getLastRequest();
        $body = $request->getBody()->getContents();

        $appSecret = $this->app->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(\hash_hmac('sha256', $body, $appSecret), $request->getHeaderLine('shopware-shop-signature'));
        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
        ], $content['source']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertNull($content['orderTransaction']['paymentMethod']['appPaymentMethod']['app']);
        static::assertArrayHasKey('queryParameters', $content);
        static::assertIsArray($content['queryParameters']);
        static::assertCount(3, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_AUTHORIZED, $transactionId);
    }

    private function addTestFinalizeCanceledState(string $token, string $transactionId, string $paymentMethodId): void
    {
        $response = (new AsyncFinalizeResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_CANCEL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $return = $this->paymentService->finalizeTransaction($token, new \Symfony\Component\HttpFoundation\Request(), $this->getSalesChannelContext($paymentMethodId));
        static::assertInstanceOf(CustomerCanceledAsyncPaymentException::class, $return->getException());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_CANCELLED, $transactionId);
    }

    private function addTestFinalizeOnlyMessage(string $token, string $transactionId, string $paymentMethodId): void
    {
        $response = (new AsyncFinalizeResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $return = $this->paymentService->finalizeTransaction($token, new \Symfony\Component\HttpFoundation\Request(), $this->getSalesChannelContext($paymentMethodId));
        static::assertInstanceOf(AsyncPaymentFinalizeException::class, $return->getException());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $transactionId);
    }

    private function getToken(string $returnUrl): ?string
    {
        $query = \parse_url($returnUrl, \PHP_URL_QUERY);
        static::assertIsString($query);

        \parse_str($query, $params);

        $token = $params['_sw_payment_token'];

        if ($token === null) {
            return null;
        }

        static::assertIsString($token);

        return $token;
    }
}
