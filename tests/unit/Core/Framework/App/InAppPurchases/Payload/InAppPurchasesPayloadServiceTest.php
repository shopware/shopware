<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\InAppPurchases\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayload;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayloadService;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\IdsCollection;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesPayloadService::class)]
#[Package('checkout')]
class InAppPurchasesPayloadServiceTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testRequest(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);

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

        $context = new Context(new SystemSource());
        $responseContent = \json_encode([
            'purchases' => [
                'purchase-1',
                'purchase-2',
            ],
        ], \JSON_THROW_ON_ERROR);

        static::assertNotFalse($responseContent);

        $filterPayloadService = new InAppPurchasesPayloadService(
            $appPayloadServiceHelper,
            new Client(['handler' => new MockHandler([new Response(200, [], $responseContent)])]),
            $this->createMock(ExceptionLogger::class),
        );

        $payload = new InAppPurchasesPayload([
            'purchase-1',
            'purchase-2',
        ]);

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.6-dev');
        $app->setAppSecret('very-secret');
        $app->setInAppPurchasesGatewayUrl('https://example.com/filter-mah-features');

        $filterResponse = $filterPayloadService->request(
            $payload,
            $app,
            $context
        );

        $actualPurchases = $filterResponse->getPurchases();
        static::assertCount(2, $actualPurchases);
        static::assertSame('purchase-1', $actualPurchases[0]);
        static::assertSame('purchase-2', $actualPurchases[1]);
    }

    public function testRequestReceiveFilteredResponse(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);

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

        $context = new Context(new SystemSource());
        $responseContent = \json_encode([
            'purchases' => [
                'purchase-2',
            ],
        ], \JSON_THROW_ON_ERROR);

        static::assertNotFalse($responseContent);

        $filterPayloadService = new InAppPurchasesPayloadService(
            $appPayloadServiceHelper,
            new Client(['handler' => new MockHandler([new Response(200, [], $responseContent)])]),
            $this->createMock(ExceptionLogger::class),
        );

        $payload = new InAppPurchasesPayload([
            'purchase-1',
            'purchase-2',
        ]);

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.6-dev');
        $app->setAppSecret('top-secret');
        $app->setInAppPurchasesGatewayUrl('https://example.com/filter-mah-features');

        $filterResponse = $filterPayloadService->request(
            $payload,
            $app,
            $context
        );

        $actualPurchases = $filterResponse->getPurchases();
        static::assertCount(1, $actualPurchases);
        static::assertSame('purchase-2', $actualPurchases[0]);
    }

    public function testGuzzleException(): void
    {
        static::expectException(TransferException::class);
        static::expectExceptionMessage('Something went wrong');

        $client = new Client([
            'handler' => function (): void {
                throw new TransferException('Something went wrong');
            },
        ]);

        $payload = $this->createMock(InAppPurchasesPayload::class);

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.6-dev');
        $app->setAppSecret('top-secret');
        $app->setInAppPurchasesGatewayUrl('https://example.com/filter-mah-features');

        $filterPayloadService = new InAppPurchasesPayloadService(
            $this->createMock(AppPayloadServiceHelper::class),
            $client,
            $this->createMock(ExceptionLogger::class),
        );

        $filterPayloadService->request(
            $payload,
            $app,
            new Context(new SystemSource())
        );
    }

    public function testAppSecretMissing(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);

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

        $context = new Context(new SystemSource());

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.5-dev');
        $app->setName('Test app');
        $app->setInAppPurchasesGatewayUrl('https://example.com/filter-mah-features');

        $filterPayloadService = new InAppPurchasesPayloadService(
            $appPayloadServiceHelper,
            new Client(),
            $this->createMock(ExceptionLogger::class),
        );

        $payload = $this->createMock(InAppPurchasesPayload::class);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App secret is missing');

        $filterPayloadService->request(
            $payload,
            $app,
            $context
        );
    }
}
