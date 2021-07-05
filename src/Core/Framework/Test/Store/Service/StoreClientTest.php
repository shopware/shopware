<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreClientTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SystemConfigTestBehaviour;
    use StoreClientBehaviour;

    private StoreClient $storeClient;

    private SystemConfigService $configService;

    private Context $storeContext;

    public function setUp(): void
    {
        $this->configService = $this->getContainer()->get(SystemConfigService::class);
        $this->storeClient = $this->getContainer()->get(StoreClient::class);

        $this->setLicenseDomain('shopware-test');

        $this->storeContext = $this->createAdminStoreContext();
    }

    public function testSignPayloadWithAppSecret(): void
    {
        $this->getRequestHandler()->append(new Response(200, [], '{"signature": "signed"}'));

        static::assertEquals('signed', $this->storeClient->signPayloadWithAppSecret('[this can be anything]', 'testApp'));

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals('/swplatform/generatesignature', $lastRequest->getUri()->getPath());

        static::assertEquals([
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => 'en-GB',
            'domain' => 'shopware-test',
        ], Query::parse($lastRequest->getUri()->getQuery()));

        static::assertEquals([
            'appName' => 'testApp',
            'payload' => '[this can be anything]',
        ], \json_decode($lastRequest->getBody()->getContents(), true));
    }

    public function testItUpdatesUserTokenAfterLogin(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], \file_get_contents(__DIR__ . '/../_fixtures/responses/login.json'))
        );

        $this->storeClient->loginWithShopwareId('shopwareId', 'password', $this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals([
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => 'en-GB',
            'domain' => 'shopware-test',
        ], Query::parse($lastRequest->getUri()->getQuery()));

        static::assertEquals([
            'shopwareId' => 'shopwareId',
            'password' => 'password',
            'shopwareUserId' => $this->storeContext->getSource()->getUserId(),
        ], \json_decode($lastRequest->getBody()->getContents(), true));

        // token from login.json
        static::assertEquals(
            'updated-token',
            $this->getStoreTokenFromContext($this->storeContext)
        );

        // secret from login.json
        static::assertEquals(
            'shop.secret',
            $this->configService->get('core.store.shopSecret')
        );

        static::assertEquals(
            'shopwareId',
            $this->configService->get('core.store.shopwareId')
        );
    }

    public function testItRequestsUpdatesForLoggedInUser(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

        $pluginList = new ExtensionCollection();
        $pluginList->add((new ExtensionStruct())->assign([
            'name' => 'TestExtension',
            'version' => '1.0.0',
        ]));

        $this->getRequestHandler()->append(new Response(200, [], \json_encode([
            'data' => [],
        ])));

        $updateList = $this->storeClient->getExtensionUpdateList($pluginList, $this->storeContext);

        static::assertEquals([], $updateList);

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals(
            $this->getStoreTokenFromContext($this->storeContext),
            $lastRequest->getHeader('X-Shopware-Platform-Token')[0],
        );
    }

    public function testItRequestsUpdateForNotLoggedInUser(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

        $this->getUserRepository()->update([
            [
                'id' => $this->storeContext->getSource()->getUserId(),
                'storeToken' => null,
            ],
        ], Context::createDefaultContext());

        $pluginList = new ExtensionCollection();
        $pluginList->add((new ExtensionStruct())->assign([
            'name' => 'TestExtension',
            'version' => '1.0.0',
        ]));

        $this->getRequestHandler()->append(new Response(200, [], \json_encode([
            'data' => [
                [
                    'name' => 'TestExtension',
                    'version' => '1.1.0',
                ],
            ],
        ])));

        $updateList = $this->storeClient->getExtensionUpdateList($pluginList, $this->storeContext);

        static::assertCount(1, $updateList);
        static::assertEquals('TestExtension', $updateList[0]->getName());
        static::assertEquals('1.1.0', $updateList[0]->getVersion());

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertFalse($lastRequest->hasHeader('X-Shopware-Platform-Token'));
    }
}
