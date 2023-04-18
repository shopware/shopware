<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\ScriptStoreApiRoute;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptStoreApiRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = $this->getSalesChannelBrowser();
    }

    public function testApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('POST', '/store-api/script/simple-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-simple-script::response', $traces);
        static::assertCount(1, $traces['store-api-simple-script::response']);
        static::assertSame('some debug information', $traces['store-api-simple-script::response'][0]['output'][0]);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_simple_script_response', $response['apiAlias']);
    }

    public function testApiEndpointWithSlashInHookName(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('POST', '/store-api/script/simple/script');
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-simple-script::response', $traces);
        static::assertCount(1, $traces['store-api-simple-script::response']);
        static::assertSame('some debug information', $traces['store-api-simple-script::response'][0]['output'][0]);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_simple_script_response', $response['apiAlias']);
    }

    public function testRepositoryCall(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $ids = new IdsCollection();

        $salesChannelId = $this->browser->getServerParameter('test-sales-channel-id');

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

        $this->browser->request('POST', '/store-api/script/repository-test', $criteria);
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $expected = [
            'apiAlias' => 'store_api_repository_test_response',
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

    public function testScriptExecutionViaGet(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('GET', '/store-api/script/repository-test');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
    }

    public function testInsufficientPermissionException(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('POST', '/store-api/script/insufficient-permissions');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('Forbidden', $response['errors'][0]['title']);
        static::assertStringContainsString('store-api-insufficient-permissions', $response['errors'][0]['detail']);
        static::assertStringContainsString('Missing privilege', $response['errors'][0]['detail']);
    }

    public function testRedirectResponse(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'p1'))->price(100)->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $this->browser->followRedirects(false);
        $this->browser->request('POST', '/store-api/script/redirect-response', ['productId' => $ids->get('p1')]);
        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        static::assertTrue($response->headers->has('location'));
        static::assertSame('/api/product/' . $ids->get('p1'), $response->headers->get('location'));
    }

    public function testCaching(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('GET', '/store-api/script/cache-script?query-param=1');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        static::assertCount(1, $traces['store-api-cache-script::response']);
        static::assertSame('some debug information', $traces['store-api-cache-script::response'][0]['output'][0]);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);

        static::assertFalse($this->browser->getResponse()->headers->has(ScriptStoreApiRoute::INVALIDATION_STATES_HEADER));

        $this->browser->request('GET', '/store-api/script/cache-script?query-param=1');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        // assert that the response was cached, and thus the script was not called again
        static::assertCount(1, $traces['store-api-cache-script::response']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);

        static::assertFalse($this->browser->getResponse()->headers->has(ScriptStoreApiRoute::INVALIDATION_STATES_HEADER));

        $this->browser->request('GET', '/store-api/script/cache-script?query-param=2');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        // assert that when the query param changes the script is executed again
        static::assertCount(2, $traces['store-api-cache-script::response']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);
    }

    public function testCachingWithCustomTags(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('GET', '/store-api/script/cache-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        static::assertCount(1, $traces['store-api-cache-script::response']);
        static::assertSame('some debug information', $traces['store-api-cache-script::response'][0]['output'][0]);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);

        $this->browser->request('GET', '/store-api/script/cache-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        // assert that the response was cached, and thus the script was not called again
        static::assertCount(1, $traces['store-api-cache-script::response']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);

        // invalidate the custom cache tag
        $cacheInvalidator = $this->getContainer()->get(CacheInvalidator::class);
        $cacheInvalidator->invalidate(['my-custom-tag'], true);

        $this->browser->request('GET', '/store-api/script/cache-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        // assert that when the cache tag was invalidated the script is executed again
        static::assertCount(2, $traces['store-api-cache-script::response']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);
    }

    public function testCachingWithInvalidationState(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('GET', '/store-api/script/cache-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        static::assertCount(1, $traces['store-api-cache-script::response']);
        static::assertSame('some debug information', $traces['store-api-cache-script::response'][0]['output'][0]);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);

        $this->browser->request('GET', '/store-api/script/cache-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        // assert that the response was cached, and thus the script was not called again
        static::assertCount(1, $traces['store-api-cache-script::response']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);

        // Login to get the `logged-in` invalidation state
        $this->login();

        $this->browser->request('GET', '/store-api/script/cache-script');
        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('store-api-cache-script::response', $traces);
        // assert that when the invalidation state is present the response is not cached
        static::assertCount(2, $traces['store-api-cache-script::response']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
        static::assertSame('store_api_cache_script_response', $response['apiAlias']);
    }

    public function testScriptExecutionWithRequestService(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->browser->request('GET', '/store-api/script/request-test');

        static::assertSame(405, $this->browser->getResponse()->getStatusCode());

        $this->browser->request('POST', '/store-api/script/request-test', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['foo' => 'bar'], \JSON_THROW_ON_ERROR));

        $content = json_decode((string) $this->browser->getResponse()->getContent(), true);

        static::assertSame([
            'apiAlias' => 'store_api_request_test_response',
            'foo' => 'bar',
        ], $content);
    }
}
