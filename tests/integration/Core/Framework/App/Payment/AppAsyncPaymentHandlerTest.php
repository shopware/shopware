<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class AppAsyncPaymentHandlerTest extends AbstractAppPaymentHandlerTestCase
{
    final public const REDIRECT_URL = 'http://payment.app/do/something';

    public function testPayOtherState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => StateMachineTransitionActions::ACTION_PAID_PARTIALLY,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PARTIALLY_PAID, $transactionId);
    }

    public function testPayFailedState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => StateMachineTransitionActions::ACTION_FAIL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . 'Payment was reported as failed.');
        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
    }

    public function testPayFailedStateWithMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => StateMachineTransitionActions::ACTION_FAIL,
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . self::ERROR_MESSAGE);
        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
    }

    public function testPayNoStateButMessage(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . self::ERROR_MESSAGE);
        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
    }

    public function testPayNoState(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
            'status' => '',
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
        $this->assertOrderTransactionState(Feature::isActive('v6.7.0.0') ? OrderTransactionStates::STATE_OPEN : OrderTransactionStates::STATE_UNCONFIRMED, $transactionId);
    }

    public function testPayWithUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
    }

    public function testPayWithWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
    }

    public function testPayWithoutRedirectResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->appendNewResponse($this->signResponse(['in' => 'valid']));

        static::assertNull($this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext));
    }

    public function testPayWithErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $this->appendNewResponse(new Response(500));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
    }

    public function testPayFinalizeWithUnsignedResponse(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertInstanceOf(ServerException::class, $return->getException());
        static::assertSame('Could not verify the authenticity of the response', $return->getException()->getMessage());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
    }

    public function testPayFinalizeWithWronglySignedResponse(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertInstanceOf(ServerException::class, $return->getException());
        static::assertSame('Could not verify the authenticity of the response', $return->getException()->getMessage());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
    }

    public function testPayFinalizeWithErrorResponse(): void
    {
        $data = $this->prepareTransaction();

        $this->appendNewResponse(new Response(500));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertInstanceOf(ServerException::class, $return->getException());
        static::assertSame('Could not verify the authenticity of the response', $return->getException()->getMessage());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
    }

    public function testPayFinalize(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_AUTHORIZE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        $request = $this->getLastRequest();
        static::assertNotNull($request);
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
        static::assertIsArray($content);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
        ], $content['source']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertNull($content['orderTransaction']['paymentMethod']['appPaymentMethod']['app']);
        static::assertArrayHasKey('requestData', $content);
        static::assertIsArray($content['requestData']);
        if (!Feature::isActive('v6.7.0.0')) {
            static::assertArrayHasKey('queryParameters', $content);
            static::assertIsArray($content['queryParameters']);
        }
        static::assertArrayHasKey('recurring', $content);
        static::assertNull($content['recurring']);
        static::assertArrayHasKey('validateStruct', $content);
        static::assertNull($content['validateStruct']);
        static::assertCount(Feature::isActive('v6.7.0.0') ? 7 : 8, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_AUTHORIZED, $data['transactionId']);
    }

    public function testPayFinalizeCanceledState(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_CANCEL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertInstanceOf(PaymentException::class, $return->getException());
        static::assertSame(PaymentException::PAYMENT_CUSTOMER_CANCELED_EXTERNAL, $return->getException()->getErrorCode());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_CANCELLED, $data['transactionId']);
    }

    public function testPayFinalizeOnlyMessage(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertInstanceOf(AppException::class, $return->getException());
        static::assertSame('The app payment process was interrupted due to the following error:' . \PHP_EOL . self::ERROR_MESSAGE, $return->getException()->getMessage());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
    }

    public function testPayFinalizeNoState(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => '',
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertNull($return->getException());
        $this->assertOrderTransactionState(Feature::isActive('v6.7.0.0') ? OrderTransactionStates::STATE_OPEN : OrderTransactionStates::STATE_UNCONFIRMED, $data['transactionId']);
    }

    /**
     * @return array{token: TokenStruct, transactionId: string, paymentMethodId: string}
     */
    private function prepareTransaction(): array
    {
        $paymentMethodId = $this->getPaymentMethodId('async');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = (new PaymentResponse())->assign([
            'redirectUrl' => self::REDIRECT_URL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $response = $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);
        static::assertNotNull($response);

        static::assertSame(self::REDIRECT_URL, $response->getTargetUrl());
        $request = $this->getLastRequest();
        static::assertNotNull($request);
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
        static::assertIsArray($content);
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
        static::assertCount(1, $content['order']['transactions']);
        // sensitive data is removed
        static::assertArrayNotHasKey('password', $content['order']['orderCustomer']['customer']);
        static::assertArrayHasKey('requestData', $content);
        static::assertIsArray($content['requestData']);
        if (!Feature::isActive('v6.7.0.0')) {
            static::assertArrayHasKey('queryParameters', $content);
            static::assertIsArray($content['queryParameters']);
        }
        static::assertNull($content['orderTransaction']['paymentMethod']['appPaymentMethod']['app']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertArrayHasKey('recurring', $content);
        static::assertNull($content['recurring']);
        static::assertArrayHasKey('validateStruct', $content);
        static::assertIsArray($content['validateStruct']);
        static::assertCount(Feature::isActive('v6.7.0.0') ? 7 : 8, $content);

        $this->assertOrderTransactionState(Feature::isActive('v6.7.0.0') ? OrderTransactionStates::STATE_OPEN : OrderTransactionStates::STATE_UNCONFIRMED, $transactionId);

        return [
            'token' => $token,
            'transactionId' => $transactionId,
            'paymentMethodId' => $paymentMethodId,
        ];
    }

    private function getToken(string $returnUrl): TokenStruct
    {
        $query = \parse_url($returnUrl, \PHP_URL_QUERY);
        static::assertIsString($query);

        \parse_str($query, $params);

        $token = $params['_sw_payment_token'];
        static::assertNotEmpty($token);
        static::assertIsString($token);

        return $this->getContainer()->get(JWTFactoryV2::class)->parseToken($token);
    }
}
