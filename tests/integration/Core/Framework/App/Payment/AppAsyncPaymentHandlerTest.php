<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
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
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);
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

        $this->appendNewResponse($this->signResponse([]));

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

    /**
     * @deprecated tag:v6.8.0 - remove this test
     */
    public function testPayFinalizeWithUnsignedResponseOldStruct(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

    public function testPayFinalizeWithUnsignedResponse(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        try {
            // @deprecated tag:v6.8.0 - replace following line with:
            // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
            $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);
        } finally {
            $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test
     */
    public function testPayFinalizeWithWronglySignedResponseOldStruct(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

    public function testPayFinalizeWithWronglySignedResponse(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        try {
            // @deprecated tag:v6.8.0 - replace following line with:
            // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
            $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);
        } finally {
            $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test
     */
    public function testPayFinalizeWithErrorResponseOldStruct(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $this->appendNewResponse(new Response(500));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertInstanceOf(ServerException::class, $return->getException());
        static::assertSame('Could not verify the authenticity of the response', $return->getException()->getMessage());

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
    }

    public function testPayFinalizeWithErrorResponse(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $this->appendNewResponse(new Response(500));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Could not verify the authenticity of the response');
        try {
            // @deprecated tag:v6.8.0 - replace following line with:
            // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
            $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);
        } finally {
            $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
        }
    }

    public function testPayFinalize(): void
    {
        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_AUTHORIZE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        // @deprecated tag:v6.8.0 - replace following line with:
        // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
        $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);

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
            'inAppPurchases' => null,
        ], $content['source']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertNull($content['orderTransaction']['paymentMethod']['appPaymentMethod']['app']);
        static::assertArrayHasKey('requestData', $content);
        static::assertIsArray($content['requestData']);
        static::assertArrayHasKey('recurring', $content);
        static::assertNull($content['recurring']);
        static::assertArrayHasKey('validateStruct', $content);
        static::assertNull($content['validateStruct']);
        static::assertCount(7, $content);
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_AUTHORIZED, $data['transactionId']);
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test
     */
    public function testPayFinalizeCanceledStateOldStruct(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

    public function testPayFinalizeCanceledState(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => StateMachineTransitionActions::ACTION_CANCEL,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The customer canceled the external payment process. ');
        try {
            // @deprecated tag:v6.8.0 - replace following line with:
            // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
            $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);
        } finally {
            $this->assertOrderTransactionState(OrderTransactionStates::STATE_CANCELLED, $data['transactionId']);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test
     */
    public function testPayFinalizeOnlyMessageOldStruct(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

    public function testPayFinalizeOnlyMessage(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'message' => self::ERROR_MESSAGE,
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The app payment process was interrupted due to the following error:' . \PHP_EOL . self::ERROR_MESSAGE);
        try {
            // @deprecated tag:v6.8.0 - replace following line with:
            // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
            $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);
        } finally {
            $this->assertOrderTransactionState(OrderTransactionStates::STATE_FAILED, $data['transactionId']);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - remove this test
     */
    public function testPayFinalizeNoStateOldStruct(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => '',
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $return = $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));

        static::assertNull($return->getException());
        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $data['transactionId']);
    }

    public function testPayFinalizeNoState(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $data = $this->prepareTransaction();

        $response = (new PaymentResponse())->assign([
            'status' => '',
        ]);
        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        // @deprecated tag:v6.8.0 - replace following line with:
        // $this->paymentProcessor->finalize($data['paymentToken'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']));
        $this->paymentProcessor->finalize($data['token'], new Request(), $this->getSalesChannelContext($data['paymentMethodId']), $data['paymentToken']);

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $data['transactionId']);
    }

    /**
     * @deprecated tag:v6.8.0 - will not return `token` anymore, `paymentToken` will not be nullable
     *
     * @return array{token: TokenStruct, transactionId: string, paymentMethodId: string, paymentToken: PaymentToken|null}
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
            'inAppPurchases' => null,
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
        static::assertNull($content['orderTransaction']['paymentMethod']['appPaymentMethod']['app']);
        static::assertArrayHasKey('orderTransaction', $content);
        static::assertIsArray($content['orderTransaction']);
        static::assertArrayHasKey('recurring', $content);
        static::assertNull($content['recurring']);
        static::assertArrayHasKey('validateStruct', $content);
        static::assertIsArray($content['validateStruct']);
        static::assertCount(7, $content);

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

        return [
            'token' => $token instanceof TokenStruct ? $token : $this->getDummyStruct(),
            'transactionId' => $transactionId,
            'paymentMethodId' => $paymentMethodId,
            'paymentToken' => $token instanceof PaymentToken ? $token : null,
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - will only return `PaymentToken`
     */
    private function getToken(string $returnUrl): TokenStruct|PaymentToken
    {
        $query = \parse_url($returnUrl, \PHP_URL_QUERY);
        static::assertIsString($query);

        \parse_str($query, $params);

        $token = $params['_sw_payment_token'];
        static::assertNotEmpty($token);
        static::assertIsString($token);

        if (!Feature::isActive('v6.8.0.0')) {
            return static::getContainer()->get(JWTFactoryV2::class)->parseToken($token);
        }

        return static::getContainer()->get(PaymentTokenGenerator::class)->decode($token);
    }

    /**
     * @deprecated tag:v6.8.0 - remove this method
     */
    private function getDummyStruct(): TokenStruct
    {
        $tokenStruct = null;
        Feature::silent('v6.8.0.0', function () use (&$tokenStruct): void {
            $tokenStruct = new TokenStruct();
        });
        static::assertInstanceOf(TokenStruct::class, $tokenStruct);

        return $tokenStruct;
    }
}
