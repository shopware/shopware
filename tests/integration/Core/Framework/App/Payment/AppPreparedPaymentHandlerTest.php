<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payment\Response\ValidateResponse;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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
            'inAppPurchases' => null,
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
}
