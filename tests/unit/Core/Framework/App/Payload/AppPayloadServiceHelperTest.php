<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayload;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(AppPayloadServiceHelper::class)]
class AppPayloadServiceHelperTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testBuildSource(): void
    {
        $shopId = ShopId::v2($this->ids->get('shop-id'));
        $inAppPurchase = StaticInAppPurchaseFactory::createWithFeatures([
            'TestApp' => ['purchase-1', 'purchase-2'],
            'AnotherApp' => ['purchase-3'],
        ]);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($shopId);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(JsonEntityEncoder::class),
            $shopIdProvider,
            $inAppPurchase,
            'https://shopware.com',
            new MockClock(),
        );

        $source = $appPayloadServiceHelper->buildSource('1.0.0', 'TestApp');

        static::assertSame('https://shopware.com', $source->getUrl());
        static::assertSame($this->ids->get('shop-id'), $source->getShopId());
        static::assertSame('1.0.0', $source->getAppVersion());
        static::assertSame('a6a4063ffda65516983ad40e8dc91db6', $source->getInAppPurchases());
    }

    public function testEncode(): void
    {
        $context = new Context(new SystemSource());
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        $cart = new Cart($this->ids->get('cart'));
        $source = new Source('https://shopware.com', $this->ids->get('shop-id'), '1.0.0');
        $payload = new TaxProviderPayload($cart, $salesChannelContext);
        $payload->setSource($source);

        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityClass')
            ->willReturn(new TaxProviderDefinition());

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $this->createMock(ShopIdProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            'https://shopware.com',
            new MockClock(),
        );

        $array = $appPayloadServiceHelper->encode($payload);

        static::assertSame(['source' => $source, 'cart' => $cart, 'context' => []], $array);
    }

    public function testCreateRequestOptionsWithNoParams(): void
    {
        $shopId = ShopId::v2($this->ids->get('shop-id'));
        $context = Context::createDefaultContext();
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityEncoder = $this->createMock(JsonEntityEncoder::class);
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($shopId);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider,
            StaticInAppPurchaseFactory::createWithFeatures(),
            'https://shopware.com',
            new MockClock(),
        );

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setAppSecret('top-secret');

        $payload = $this->createMock(SourcedPayloadInterface::class);
        $payload
            ->expects($this->once())
            ->method('setSource')
            ->with(static::isInstanceOf(Source::class));
        $payload
            ->method('jsonSerialize')
            ->willReturn(['key' => 'value']);

        $jsonPayload = $appPayloadServiceHelper->createRequestOptions($payload, $app, $context)->jsonSerialize();

        static::assertSame($context, $jsonPayload[AuthMiddleware::APP_REQUEST_CONTEXT]);
        static::assertSame('top-secret', $jsonPayload[AuthMiddleware::APP_REQUEST_TYPE][AuthMiddleware::APP_SECRET]);
        static::assertSame(['Content-Type' => 'application/json'], $jsonPayload['headers']);
        static::assertJsonStringEqualsJsonString('{"key":"value"}', $jsonPayload['body']);
    }

    public function testCreateRequestOptionsWithAdditionalParams(): void
    {
        $shopId = ShopId::v2($this->ids->get('shop-id'));
        $context = Context::createDefaultContext();
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityEncoder = $this->createMock(JsonEntityEncoder::class);
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($shopId);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider,
            StaticInAppPurchaseFactory::createWithFeatures(),
            'https://shopware.com',
            new MockClock(),
        );

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setAppSecret('top-secret');

        $payload = $this->createMock(SourcedPayloadInterface::class);
        $payload
            ->expects($this->once())
            ->method('setSource')
            ->with(static::isInstanceOf(Source::class));
        $payload
            ->method('jsonSerialize')
            ->willReturn(['key' => 'value']);

        $jsonPayload = $appPayloadServiceHelper->createRequestOptions($payload, $app, $context, ['timeout' => 50])->jsonSerialize();

        static::assertSame($context, $jsonPayload[AuthMiddleware::APP_REQUEST_CONTEXT]);
        static::assertSame('top-secret', $jsonPayload[AuthMiddleware::APP_REQUEST_TYPE][AuthMiddleware::APP_SECRET]);
        static::assertSame(['Content-Type' => 'application/json'], $jsonPayload['headers']);
        static::assertArrayHasKey('timeout', $jsonPayload);
        static::assertSame(50, $jsonPayload['timeout']);
        static::assertJsonStringEqualsJsonString('{"key":"value"}', $jsonPayload['body']);
    }

    public function testCreateRequestOptionsThrowsExceptionWhenNoAppSecret(): void
    {
        $this->expectExceptionObject(AppException::registrationFailed('TestApp', 'App secret is missing'));

        $context = Context::createDefaultContext();
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityEncoder = $this->createMock(JsonEntityEncoder::class);
        $shopIdProvider = $this->createMock(ShopIdProvider::class);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider,
            StaticInAppPurchaseFactory::createWithFeatures(),
            'https://shopware.com',
            new MockClock(),
        );

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setName('TestApp');

        $payload = $this->createMock(SourcedPayloadInterface::class);

        $appPayloadServiceHelper->createRequestOptions($payload, $app, $context);
    }

    public function testCreateWebhookRequestWithAllParams(): void
    {
        $clock = new MockClock('2026-01-15 12:00:00');
        $helper = $this->createHelper($clock);

        $result = $helper->createWebhookRequest(
            ['data' => 'value'],
            'https://hook.example.com',
            '6.7.0',
            10,
            20,
            'my-secret',
            'lang-id-123',
            'en-GB',
            ['X-Custom' => 'header-val'],
        );

        $body = json_decode($result->body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('value', $body['data']);
        static::assertSame($clock->now()->getTimestamp(), $body['timestamp']);
        static::assertSame($clock->now()->getTimestamp(), $result->timestamp);

        // Headers
        static::assertSame('application/json', $result->headers['Content-Type']);
        static::assertSame('6.7.0', $result->headers['sw-version']);
        static::assertSame('header-val', $result->headers['X-Custom']);
        static::assertSame('lang-id-123', $result->headers[AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE]);
        static::assertSame('en-GB', $result->headers[AuthMiddleware::SHOPWARE_USER_LANGUAGE]);

        // PSR-7 request
        static::assertSame('POST', $result->request->getMethod());
        static::assertSame('https://hook.example.com', (string) $result->request->getUri());

        // Options with secret
        static::assertSame(10, $result->options['connect_timeout']);
        static::assertSame(20, $result->options['timeout']);
        static::assertSame('my-secret', $result->options[AuthMiddleware::APP_REQUEST_TYPE][AuthMiddleware::APP_SECRET]);
    }

    public function testCreateWebhookRequestWithoutSecret(): void
    {
        $helper = $this->createHelper(new MockClock());

        $result = $helper->createWebhookRequest(
            ['data' => 'value'],
            'https://hook.example.com',
            '6.7.0',
            10,
            20,
        );

        static::assertArrayNotHasKey(AuthMiddleware::APP_REQUEST_TYPE, $result->options);
        static::assertSame(10, $result->options['connect_timeout']);
        static::assertSame(20, $result->options['timeout']);
    }

    public function testCreateWebhookRequestWithoutLanguageHeaders(): void
    {
        $helper = $this->createHelper(new MockClock());

        $result = $helper->createWebhookRequest(
            ['data' => 'value'],
            'https://hook.example.com',
            '6.7.0',
            10,
            20,
        );

        static::assertArrayNotHasKey(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE, $result->headers);
        static::assertArrayNotHasKey(AuthMiddleware::SHOPWARE_USER_LANGUAGE, $result->headers);
    }

    private function createHelper(MockClock $clock): AppPayloadServiceHelper
    {
        return new AppPayloadServiceHelper(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(JsonEntityEncoder::class),
            $this->createMock(ShopIdProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            'https://shopware.com',
            $clock,
        );
    }
}
