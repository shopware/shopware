<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class AppRecurringHandlerTest extends AbstractAppPaymentHandlerTestCase
{
    public function testRecurring(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([
            'status' => OrderTransactionStates::STATE_PAID,
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $paymentHandler = $this->getContainer()->get(AppPaymentHandler::class);
        $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());

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
        $content = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId(),
            'appVersion' => '1.0.0',
        ], $content['source']);

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PAID, $transactionId);
    }

    public function testItFailsOnErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([
            'message' => 'FOO_BAR_ERROR_MESSAGE',
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $paymentHandler = $this->getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(AppException::class, $e);
            static::assertSame('The app payment process was interrupted due to the following error:
FOO_BAR_ERROR_MESSAGE', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    public function testItFailsOnUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $paymentHandler = $this->getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(ServerException::class, $e);
            static::assertSame('Could not verify the authenticity of the response', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    public function testItFailsOnWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['shopware-app-signature' => 'invalid'], $json));

        $paymentHandler = $this->getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(ServerException::class, $e);
            static::assertSame('Could not verify the authenticity of the response', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    private function getRecurringStruct(): PaymentTransactionStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $this->ids->get('order')));

        $transactionId = $this->orderTransactionRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($transactionId);

        return new PaymentTransactionStruct($transactionId);
    }
}
