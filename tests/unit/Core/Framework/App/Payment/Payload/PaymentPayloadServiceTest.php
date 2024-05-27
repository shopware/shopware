<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayload;
use Shopware\Core\Framework\App\Payment\Response\SyncPayResponse;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayloadInterface;
use Shopware\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopware\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\IdsCollection;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentPayloadService::class)]
class PaymentPayloadServiceTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private AppPayloadServiceHelper&MockObject $helper;

    private PaymentPayloadService $service;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->client = $this->createMock(ClientInterface::class);
        $this->helper = $this->createMock(AppPayloadServiceHelper::class);
        $this->service = new PaymentPayloadService($this->helper, $this->client);
    }

    public function testRequest(): void
    {
        $definition = new OrderTransactionDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityName')
            ->willReturn($definition);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($this->ids->get('shop-id'));

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider,
            'https://test-shop.com'
        );

        $response = \json_encode(['status' => 'paid'], \JSON_THROW_ON_ERROR);

        $client = new Client(['handler' => new MockHandler([new Response(200, [], $response)])]);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($this->ids->get('transaction'));
        $payload = new PaymentPayload($transaction, new OrderEntity());

        $app = new AppEntity();
        $app->setName('foo');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $service = new PaymentPayloadService($appPayloadServiceHelper, $client);

        $gatewayResponse = $service->request(
            'https://example.com',
            $payload,
            $app,
            PaymentResponse::class,
            Context::createDefaultContext()
        );

        static::assertInstanceOf(PaymentResponse::class, $gatewayResponse);
        static::assertSame('paid', $gatewayResponse->getStatus());
    }

    public function testRequestReturnsExpectedResponse(): void
    {
        $payload = $this->createMock(PaymentPayloadInterface::class);
        $app = new AppEntity();
        $app->setName('InsecureApp');
        $app->setAppSecret('secret');

        $context = Context::createDefaultContext();

        $this->helper
            ->expects(static::once())
            ->method('encode')
            ->with($payload)
            ->willReturn([]);

        $this->helper
            ->expects(static::once())
            ->method('buildSource')
            ->with($app)
            ->willReturn(new Source('shopurl', 'shopid', '0.0.0'));

        $this->client
            ->expects(static::once())
            ->method('request')
            ->with('POST', 'http://example.com', [
                AuthMiddleware::APP_REQUEST_CONTEXT => $context,
                AuthMiddleware::APP_REQUEST_TYPE => [
                    AuthMiddleware::APP_SECRET => 'secret',
                    AuthMiddleware::VALIDATED_RESPONSE => true,
                ],
                'timeout' => PaymentPayloadService::PAYMENT_REQUEST_TIMEOUT,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => '[]',
            ])
            ->willReturn(new Response(200, [], json_encode(['message' => 'foo'], \JSON_THROW_ON_ERROR)));

        $response = $this->service->request(
            'http://example.com',
            $payload,
            $app,
            PaymentResponse::class,
            $context,
        );

        static::assertInstanceOf(PaymentResponse::class, $response);
        static::assertSame('foo', $response->getErrorMessage());
    }

    public function testRequestThrowsExceptionWhenAppSecretIsMissing(): void
    {
        $app = new AppEntity();
        $app->setName('InsecureApp');
        $app->setAppSecret(null);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App registration for "InsecureApp" failed: App secret is missing');
        $this->service->request(
            'http://example.com',
            $this->createMock(PaymentPayloadInterface::class),
            $app,
            AbstractResponse::class,
            Context::createDefaultContext()
        );

        static::assertInstanceOf(SyncPayResponse::class, $gatewayResponse);
        static::assertSame('paid', $gatewayResponse->getStatus());
        static::assertSame('test-message', $gatewayResponse->getMessage());
    }
}
