<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Payment;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Exception\RefundException;
use Shopware\Core\Checkout\Payment\Exception\UnknownRefundHandlerException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\RefundResponse;

/**
 * @internal
 */
class AppRefundHandlerTest extends AbstractAppPaymentHandlerTest
{
    public function testRefund(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('refundable');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $captureId = $this->createCapture($transactionId);
        $refundId = $this->createRefund($captureId);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = RefundResponse::create($transactionId, [
            'status' => 'complete',
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $this->paymentRefundProcessor->processRefund($refundId, $salesChannelContext->getContext());

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

        static::assertArrayHasKey('refund', $content);
        static::assertIsArray($content['refund']);
        $this->assertRefundState(OrderTransactionCaptureRefundStates::STATE_COMPLETED, $refundId);
    }

    public function testItFailsOnErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('refundable');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $captureId = $this->createCapture($transactionId);
        $refundId = $this->createRefund($captureId);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = RefundResponse::create($transactionId, [
            'message' => 'FOO_BAR_ERROR_MESSAGE',
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        try {
            $this->paymentRefundProcessor->processRefund($refundId, $salesChannelContext->getContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(RefundException::class, $e);
            static::assertSame('The refund process was interrupted due to the following error:
FOO_BAR_ERROR_MESSAGE', $e->getMessage());

            $this->assertRefundState(OrderTransactionCaptureRefundStates::STATE_FAILED, $refundId);

            return;
        }

        static::fail('Should catch a RefundException');
    }

    public function testItFailsOnUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('refundable');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $captureId = $this->createCapture($transactionId);
        $refundId = $this->createRefund($captureId);

        $response = RefundResponse::create($transactionId, []);

        $this->appendNewResponse(new Response(200, [], \json_encode($response)));

        $context = $this->getSalesChannelContext($paymentMethodId);

        try {
            $this->paymentRefundProcessor->processRefund($refundId, $context->getContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(RefundException::class, $e);
            static::assertSame('The refund process was interrupted due to the following error:
Invalid app response', $e->getMessage());

            $this->assertRefundState(OrderTransactionCaptureRefundStates::STATE_FAILED, $refundId);

            return;
        }

        static::fail('Should catch a RefundException');
    }

    public function testItFailsOnWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('refundable');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $captureId = $this->createCapture($transactionId);
        $refundId = $this->createRefund($captureId);

        $response = RefundResponse::create($transactionId, []);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], json_encode($response)));

        $context = $this->getSalesChannelContext($paymentMethodId);

        try {
            $this->paymentRefundProcessor->processRefund($refundId, $context->getContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(RefundException::class, $e);
            static::assertSame('The refund process was interrupted due to the following error:
Invalid app response', $e->getMessage());

            $this->assertRefundState(OrderTransactionCaptureRefundStates::STATE_FAILED, $refundId);

            return;
        }

        static::fail('Should catch a RefundException');
    }

    public function testItThrowsOnNonRefundable(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('sync');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);
        $captureId = $this->createCapture($transactionId);
        $refundId = $this->createRefund($captureId);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = RefundResponse::create($transactionId, [
            'status' => 'complete',
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        static::expectException(UnknownRefundHandlerException::class);

        $this->paymentRefundProcessor->processRefund($refundId, $salesChannelContext->getContext());
    }
}
