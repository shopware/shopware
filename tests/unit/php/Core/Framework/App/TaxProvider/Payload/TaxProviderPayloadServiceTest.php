<?php declare(strict_types=1);

namespace unit\php\Core\Framework\App\TaxProvider\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayload;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopware\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\TaxProvider\TaxProviderDefinition;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @package checkout
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService
 */
class TaxProviderPayloadServiceTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testRequest(): void
    {
        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityClass')
            ->willReturn(new TaxProviderDefinition());

        $shopIdProvider = static::createMock(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($this->ids->get('shop-id'));

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider
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
        $response = new Response(200, [], $responseContent);

        $client = static::createMock(Client::class);
        $client
            ->expects(static::once())
            ->method('post')
            ->with($url, static::isType('array'))
            ->willReturn($response);

        $taxProviderPayloadService = new TaxProviderPayloadService(
            $appPayloadServiceHelper,
            $client,
            'https://test-shop.com'
        );

        $cart = new Cart($this->ids->get('cart'));
        $salesChannelContext = static::createMock(SalesChannelContext::class);
        $payload = new TaxProviderPayload($cart, $salesChannelContext);

        $app = static::createMock(AppEntity::class);
        $app
            ->method('getVersion')
            ->willReturn('6.5-dev');
        $app
            ->method('getAppSecret')
            ->willReturn('very-secret');

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
        static::assertCount(1, $taxes);
        static::assertSame(19.0, $taxes->first()->getTax());
        static::assertSame(19.0, $taxes->first()->getTaxRate());
        static::assertSame(100.0, $taxes->first()->getPrice());

        $deliveryTaxes = $taxResponse->getDeliveryTaxes();
        static::assertNotNull($deliveryTaxes);
        static::assertArrayHasKey($this->ids->get('delivery-1'), $deliveryTaxes);
        $taxes = $deliveryTaxes[$this->ids->get('delivery-1')];
        static::assertCount(1, $taxes);
        static::assertSame(7.0, $taxes->first()->getTax());
        static::assertSame(7.0, $taxes->first()->getTaxRate());
        static::assertSame(100.0, $taxes->first()->getPrice());

        $cartPriceTaxes = $taxResponse->getCartPriceTaxes();
        static::assertNotNull($cartPriceTaxes);
        static::assertCount(1, $cartPriceTaxes);
        static::assertSame(26.0, $cartPriceTaxes->first()->getTax());
        static::assertSame(13.0, $cartPriceTaxes->first()->getTaxRate());
        static::assertSame(200.0, $cartPriceTaxes->first()->getPrice());
    }

    public function testGuzzleException(): void
    {
        $client = static::createMock(Client::class);
        $client
            ->expects(static::once())
            ->method('post')
            ->willThrowException(new TransferException('Something went wrong'));

        $payload = static::createMock(TaxProviderPayload::class);

        $app = static::createMock(AppEntity::class);
        $app
            ->method('getVersion')
            ->willReturn('6.5-dev');
        $app
            ->method('getAppSecret')
            ->willReturn('very-secret');

        $taxProviderPayloadService = new TaxProviderPayloadService(
            static::createMock(AppPayloadServiceHelper::class),
            $client,
            'https://test-shop.com'
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
        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $definitionInstanceRegistry
            ->method('getByEntityClass')
            ->willReturn(new TaxProviderDefinition());

        $shopIdProvider = static::createMock(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($this->ids->get('shop-id'));

        $entityEncoder = new JsonEntityEncoder(
            new Serializer([new StructNormalizer()], [new JsonEncoder()])
        );

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $shopIdProvider
        );

        $url = 'https://example.com/provide-tax';
        $context = new Context(new SystemSource());
        $client = static::createMock(Client::class);

        $app = static::createMock(AppEntity::class);
        $app
            ->method('getVersion')
            ->willReturn('6.5-dev');

        $taxProviderPayloadService = new TaxProviderPayloadService(
            $appPayloadServiceHelper,
            $client,
            'https://test-shop.com'
        );

        $payload = static::createMock(TaxProviderPayload::class);

        static::expectException(AppRegistrationException::class);
        static::expectExceptionMessage('App secret missing');

        $taxProviderPayloadService->request(
            $url,
            $payload,
            $app,
            $context
        );
    }
}
