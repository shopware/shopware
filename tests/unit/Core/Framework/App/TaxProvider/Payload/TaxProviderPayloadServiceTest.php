<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayload;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopware\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxProviderPayloadService::class)]
class TaxProviderPayloadServiceTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testRequest(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityClass')
            ->willReturn(new TaxProviderDefinition());

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

        $url = 'https://example.com/provide-tax';
        $context = new Context(new SystemSource());
        $responseContent = \json_encode([
            'lineItemTaxes' => [
                $this->ids->get('line-item-1') => [
                    [
                        'tax' => 19,
                        'taxRate' => 19,
                        'price' => 100,
                    ],
                ],
            ],
            'deliveryTaxes' => [
                $this->ids->get('delivery-1') => [
                    [
                        'tax' => 7,
                        'taxRate' => 7,
                        'price' => 100,
                    ],
                ],
            ],
            'cartPriceTaxes' => [
                [
                    'tax' => 26,
                    'taxRate' => 13,
                    'price' => 200,
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        static::assertNotFalse($responseContent);

        $taxProviderPayloadService = new TaxProviderPayloadService(
            $appPayloadServiceHelper,
            new Client(['handler' => new MockHandler([new Response(200, [], $responseContent)])]),
        );

        $cart = new Cart($this->ids->get('cart'));
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $payload = new TaxProviderPayload($cart, $salesChannelContext);

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.5-dev');
        $app->setAppSecret('very-secret');

        $taxResponse = $taxProviderPayloadService->request(
            $url,
            $payload,
            $app,
            $context
        );

        static::assertInstanceOf(TaxProviderResponse::class, $taxResponse);

        $lineItemTaxes = $taxResponse->getLineItemTaxes();
        static::assertNotNull($lineItemTaxes);
        static::assertArrayHasKey($this->ids->get('line-item-1'), $lineItemTaxes);
        $taxes = $lineItemTaxes[$this->ids->get('line-item-1')];
        $tax = $taxes->first();
        static::assertInstanceOf(CalculatedTax::class, $tax);
        static::assertCount(1, $taxes);
        static::assertSame(19.0, $tax->getTax());
        static::assertSame(19.0, $tax->getTaxRate());
        static::assertSame(100.0, $tax->getPrice());

        $deliveryTaxes = $taxResponse->getDeliveryTaxes();
        static::assertNotNull($deliveryTaxes);
        static::assertArrayHasKey($this->ids->get('delivery-1'), $deliveryTaxes);
        $taxes = $deliveryTaxes[$this->ids->get('delivery-1')];
        $tax = $taxes->first();
        static::assertInstanceOf(CalculatedTax::class, $tax);
        static::assertCount(1, $taxes);
        static::assertSame(7.0, $tax->getTax());
        static::assertSame(7.0, $tax->getTaxRate());
        static::assertSame(100.0, $tax->getPrice());

        $cartPriceTaxes = $taxResponse->getCartPriceTaxes();
        static::assertNotNull($cartPriceTaxes);
        $cartPriceTax = $cartPriceTaxes->first();
        static::assertInstanceOf(CalculatedTax::class, $cartPriceTax);
        static::assertCount(1, $cartPriceTaxes);
        static::assertSame(26.0, $cartPriceTax->getTax());
        static::assertSame(13.0, $cartPriceTax->getTaxRate());
        static::assertSame(200.0, $cartPriceTax->getPrice());
    }

    public function testGuzzleException(): void
    {
        $client = new Client([
            'handler' => function (): void {
                throw new TransferException('Something went wrong');
            },
        ]);

        $payload = $this->createMock(TaxProviderPayload::class);

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.5-dev');
        $app->setAppSecret('very-secret');

        $taxProviderPayloadService = new TaxProviderPayloadService(
            $this->createMock(AppPayloadServiceHelper::class),
            $client,
        );

        $response = $taxProviderPayloadService->request(
            'https://example.com/provide-tax',
            $payload,
            $app,
            new Context(new SystemSource())
        );

        static::assertNull($response);
    }

    public function testAppSecretMissing(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityClass')
            ->willReturn(new TaxProviderDefinition());

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

        $url = 'https://example.com/provide-tax';
        $context = new Context(new SystemSource());

        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setVersion('6.5-dev');
        $app->setName('Test app');

        $taxProviderPayloadService = new TaxProviderPayloadService(
            $appPayloadServiceHelper,
            new Client(),
        );

        $payload = $this->createMock(TaxProviderPayload::class);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App secret is missing');

        $taxProviderPayloadService->request(
            $url,
            $payload,
            $app,
            $context
        );
    }
}
