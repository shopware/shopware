<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Api;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class AppActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;

    public function testGetActionsPerViewEmpty(): void
    {
        $url = '/api/app-system/action-button/product/index';
        $this->getBrowser()->request('GET', $url);

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertArrayHasKey('actions', $response);
        static::assertEmpty($response['actions']);
    }

    public function testGetActionsPerView(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $url = '/api/app-system/action-button/order/detail';
        $this->getBrowser()->request('GET', $url);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $result = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('actions', $result);

        $result = $result['actions'];
        static::assertCount(1, $result);
        static::assertTrue(Uuid::isValid($result[0]['id']));
        unset($result[0]['id']);

        $icon = \file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png');
        static::assertNotFalse($icon);

        static::assertEquals([
            [
                'app' => 'test',
                'label' => [
                    'en-GB' => 'View Order',
                    'de-DE' => 'Zeige Bestellung',
                ],
                'action' => 'viewOrder',
                'url' => 'https://swag-test.com/your-order',
                'icon' => base64_encode($icon),
            ],
        ], $result);
    }

    public function testRunAction(): void
    {
        /** @var EntityRepository<ActionButtonCollection> $actionRepo */
        $actionRepo = $this->getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $action = $actionRepo->search($criteria, Context::createDefaultContext())->getEntities();
        $action = $action->first();
        static::assertNotNull($action);

        $url = '/api/app-system/action-button/run/' . $action->getId();

        $ids = [Uuid::randomHex()];
        $postData = [
            'ids' => $ids,
        ];

        $this->appendNewResponse(new Response(200));

        $postData = \json_encode($postData);
        static::assertNotFalse($postData);
        static::assertJson($postData);

        $this->getBrowser()->request('POST', $url, [], [], [], $postData);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        $shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);

        $app = $action->getApp();
        static::assertNotNull($app);

        $expectedSource = [
            'url' => getenv('APP_URL'),
            'appVersion' => $app->getVersion(),
            'shopId' => $shopIdProvider->getShopId(),
        ];
        $expectedData = [
            'ids' => $ids,
            'action' => $action->getAction(),
            'entity' => $action->getEntity(),
        ];

        static::assertEquals($expectedSource, $data['source']);
        static::assertEquals($expectedData, $data['data']);
        static::assertNotEmpty($data['meta']['timestamp']);
        static::assertTrue(Uuid::isValid($data['meta']['reference']));
    }

    public function testRunActionEmpty(): void
    {
        /** @var EntityRepository<ActionButtonCollection> $actionRepo */
        $actionRepo = $this->getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app');

        $action = $actionRepo->search($criteria, Context::createDefaultContext())->getEntities();
        $action = $action->first();
        static::assertNotNull($action);

        $url = '/api/app-system/action-button/run/' . $action->getId();

        $postData = \json_encode(['ids' => []]);
        static::assertNotFalse($postData);
        static::assertJson($postData);

        $this->appendNewResponse(new Response(200));
        $this->getBrowser()->request('POST', $url, [], [], [], $postData);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        $expectedData = [
            'ids' => [],
            'action' => $action->getAction(),
            'entity' => $action->getEntity(),
        ];

        static::assertEquals($expectedData, $data['data']);
    }

    public function testRunInvalidAction(): void
    {
        $url = '/api/app-system/action-button/run/' . Uuid::randomHex();

        $postData = \json_encode(['ids' => []]);
        static::assertNotFalse($postData);
        static::assertJson($postData);

        $this->getBrowser()->request('POST', $url, [], [], [], $postData);

        static::assertSame(404, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testRunActionWithCustomScriptEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/action-button-script');

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('entity', 'product'),
            new EqualsFilter('view', 'list'),
        );

        $actionId = $this->getContainer()
            ->get('app_action_button.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        $this->getBrowser()->request(
            'POST',
            '/api/app-system/action-button/run/' . $actionId,
            [
                'ids' => [Uuid::randomHex()],
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertNotFalse($response->getContent());

        $payload = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals([
            'actionType' => 'notification',
            'status' => 'success',
            'message' => 'You selected 1 products.',
            'extensions' => [],
        ], $payload);
    }

    public function testGetModules(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $url = '/api/app-system/modules';
        $this->getBrowser()->request('GET', $url);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $result = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // the query strings of the sources contain non-deterministic values like timestamps
        // they are validated in `\Swag\SaasConnect\Test\Core\Content\App\Action\ModuleLoaderTest::validateSources`
        // so we remove them here and don't check them
        $result = $this->removeQueryStringsFromResult($result);

        static::assertEquals([
            'modules' => [
                [
                    'name' => 'test',
                    'label' => [
                        'en-GB' => 'Swag App Test',
                        'de-DE' => 'Swag App Test',
                    ],
                    'modules' => [
                        [
                            'label' => [
                                'en-GB' => 'My first own module',
                                'de-DE' => 'Mein erstes eigenes Modul',
                            ],
                            'source' => 'https://test.com',
                            'name' => 'first-module',
                            'parent' => 'sw-test-structure-module',
                            'position' => 10,
                        ],
                        [
                            'label' => [
                                'en-GB' => 'My menu entry for modules',
                                'de-DE' => 'Mein Menüeintrag für Module',
                            ],
                            'source' => null,
                            'name' => 'structure-module',
                            'parent' => 'sw-catalogue',
                            'position' => 50,
                        ],
                    ],
                    'mainModule' => [
                        'source' => 'https://main-module',
                    ],
                ],
            ],
        ], $result);
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function removeQueryStringsFromResult(array $result): array
    {
        $result['modules'][0]['modules'][0]['source'] = preg_replace(
            '/\?.*/',
            '',
            (string) $result['modules'][0]['modules'][0]['source']
        );

        $result['modules'][0]['mainModule']['source'] = preg_replace(
            '/\?.*/',
            '',
            (string) $result['modules'][0]['mainModule']['source']
        );

        return $result;
    }
}
