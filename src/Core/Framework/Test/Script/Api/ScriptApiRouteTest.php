<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptApiRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use AdminApiTestBehaviour;

    public function testApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->kernelBrowser = null;
        $browser = $this->getBrowser();
        $browser->request('POST', '/api/script/simple-script');

        static::assertNotFalse($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), print_r($response, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('api-simple-script', $traces);
        static::assertCount(1, $traces['api-simple-script']);
        static::assertSame('some debug information', $traces['api-simple-script'][0]['output'][0]);

        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
    }

    public function testApiEndpointWithSlashInHookName(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/script/simple/script');

        static::assertNotFalse($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), print_r($response, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('api-simple-script', $traces);
        static::assertCount(1, $traces['api-simple-script']);
        static::assertSame('some debug information', $traces['api-simple-script'][0]['output'][0]);

        static::assertArrayHasKey('foo', $response);
        static::assertEquals('bar', $response['foo']);
    }

    public function testAppNotAllowed(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $browser = $this->getBrowser(true, [], ['app.shop-owner']);
        $browser->request('POST', '/api/script/simple-script');

        static::assertNotFalse($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__PERMISSION_DENIED', $response['errors'][0]['code']);

        $this->kernelBrowser = null;
        $browser = $this->getBrowser(true, [], ['app.all']);
        $browser->request('POST', '/api/script/simple-script');
        static::assertEquals(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $this->kernelBrowser = null;
        $browser = $this->getBrowser(true, [], ['app.api-endpoint-cases']);
        $browser->request('POST', '/api/script/simple-script');
        static::assertEquals(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
    }

    public function testRepositoryCall(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'p1'))->price(100)->build(),
            (new ProductBuilder($ids, 'p2'))->price(200)->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $criteria = [
            'filter' => [
                ['type' => 'equals', 'field' => 'productNumber', 'value' => 'p1'],
            ],
            'includes' => [
                'dal_entity_search_result' => ['elements'],
                'product' => ['id', 'productNumber'],
            ],
            'limit' => 1,
        ];

        $json = \json_encode($criteria);
        static::assertNotFalse($json);

        $this->kernelBrowser = null;
        $browser = $this->getBrowser();
        $browser->request('POST', '/api/script/repository-test', [], [], [], $json);

        static::assertNotFalse($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $expected = [
            'apiAlias' => 'api_repository_test_response',
            'products' => [
                'apiAlias' => 'dal_entity_search_result',
                'elements' => [
                    ['id' => $ids->get('p1'), 'productNumber' => 'p1', 'apiAlias' => 'product'],
                ],
            ],
        ];

        static::assertEquals($expected, $response);
    }

    public function testInsufficientPermissionException(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->kernelBrowser = null;
        $browser = $this->getBrowser();
        $browser->request('POST', '/api/script/insufficient-permissions');

        static::assertEquals(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertNotFalse($browser->getResponse()->getContent());

        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('Forbidden', $response['errors'][0]['title']);
        static::assertStringContainsString('api-insufficient-permissions', $response['errors'][0]['detail']);
        static::assertStringContainsString('Missing privilege', $response['errors'][0]['detail']);
    }

    public function testMissingAclPrivilegesToAccessRoute(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $browser = $this->getBrowser();
        // no admin permissions
        $this->authorizeBrowser($browser, [], []);
        $browser->request('POST', '/api/script/simple-script');
        static::assertNotFalse($browser->getResponse()->getContent());

        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), print_r($response, true));

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('Forbidden', $response['errors'][0]['title']);
        static::assertEquals('The user does not have the permission to do this action.', $response['errors'][0]['detail']);
    }

    public function testAccessFromAppIntegrationIsAllowed(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'api-endpoint-cases'));
        /** @var AppEntity $app */
        $app = $this->getContainer()->get('app.repository')->search($criteria, Context::createDefaultContext())->first();

        $browser = $this->getBrowserAuthenticatedWithIntegration($app->getIntegrationId());
        $browser->request('POST', '/api/script/simple-script');
        static::assertNotFalse($browser->getResponse()->getContent());

        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), print_r($response, true));
    }

    public function testRedirectResponse(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'p1'))->price(100)->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $json = \json_encode(['productId' => $ids->get('p1')], \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $browser = $this->getBrowser();
        $browser->followRedirects(false);
        $browser->request('POST', '/api/script/redirect-response', [], [], [], $json);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        static::assertTrue($response->headers->has('location'));
        static::assertSame('/api/product/' . $ids->get('p1'), $response->headers->get('location'));
    }

    public function testAccessToInnerSymfonyResponseIsProhibited(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'p1'))->price(100)->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $json = \json_encode(['productId' => $ids->get('p1')], \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $browser = $this->getBrowser();
        $browser->followRedirects(false);
        $browser->request('POST', '/api/script/access-inner', [], [], [], $json);
        $response = $browser->getResponse();
        static::assertNotFalse($response->getContent());

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $content = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $content);
        static::assertCount(1, $content['errors']);
        static::assertEquals('FRAMEWORK__HOOK_METHOD_EXCEPTION', $content['errors'][0]['code']);
    }
}
