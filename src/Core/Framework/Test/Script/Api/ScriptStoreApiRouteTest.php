<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

class ScriptStoreApiRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $browser = $this->getSalesChannelBrowser();
        $browser->request('POST', '/store-api/script/simple-script');

        $response = \json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), print_r($response, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-simple-script', $traces);
        static::assertCount(1, $traces['store-api-simple-script']);
        static::assertSame('some debug information', $traces['store-api-simple-script'][0]['output'][0]);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_simple-script_response', $response['apiAlias']);
    }

    public function testRepositoryCall(): void
    {
        $browser = $this->getSalesChannelBrowser();

        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $ids = new IdsCollection();

        $salesChannelId = $browser->getServerParameter('test-sales-channel-id');

        $products = [
            (new ProductBuilder($ids, 'p1'))->visibility($salesChannelId)->price(100)->build(),
            (new ProductBuilder($ids, 'p2'))->visibility($salesChannelId)->price(200)->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $criteria = [
            'filter' => [
                ['type' => 'equals', 'field' => 'productNumber', 'value' => 'p1'],
            ],
            'includes' => [
                'dal_entity_search_result' => ['elements'],
                'product' => ['id', 'productNumber', 'calculatedPrice'],
                'calculated_price' => ['unitPrice'],
            ],
            'limit' => 1,
        ];

        $browser->request('POST', '/store-api/script/repository-test', $criteria);
        $response = \json_decode($browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $expected = [
            'apiAlias' => 'store_api_repository-test_response',
            'products' => [
                'apiAlias' => 'dal_entity_search_result',
                'elements' => [
                    [
                        'id' => $ids->get('p1'),
                        'productNumber' => 'p1',
                        'calculatedPrice' => ['unitPrice' => 100, 'apiAlias' => 'calculated_price'],
                        'apiAlias' => 'product',
                    ],
                ],
            ],
        ];

        static::assertEquals($expected, $response);
    }

    public function testInsufficientPermissionException(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $browser = $this->getSalesChannelBrowser();
        $browser->request('POST', '/store-api/script/insufficient-permissions');
        $response = \json_decode($browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), print_r($response, true));

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('Forbidden', $response['errors'][0]['title']);
        static::assertStringContainsString('store-api-insufficient-permissions', $response['errors'][0]['detail']);
        static::assertStringContainsString('Missing privilege', $response['errors'][0]['detail']);
    }
}
