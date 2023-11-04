<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('merchant-services')]
class StoreClientTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private StoreClient $storeClient;

    private SystemConfigService $configService;

    private Context $storeContext;

    protected function setUp(): void
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
        static::assertInstanceOf(RequestInterface::class, $lastRequest);

        static::assertEquals('/swplatform/generatesignature', $lastRequest->getUri()->getPath());

        static::assertEquals([
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => 'en-GB',
            'domain' => 'shopware-test',
        ], Query::parse($lastRequest->getUri()->getQuery()));

        static::assertEquals([
            'appName' => 'testApp',
            'payload' => '[this can be anything]',
        ], \json_decode($lastRequest->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testItUpdatesUserTokenAfterLogin(): void
    {
        $responseBody = \file_get_contents(__DIR__ . '/../_fixtures/responses/login.json');
        static::assertIsString($responseBody);

        $this->getRequestHandler()->append(
            new Response(200, [], $responseBody)
        );

        $this->storeClient->loginWithShopwareId('shopwareId', 'password', $this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);

        static::assertEquals([
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => 'en-GB',
            'domain' => 'shopware-test',
        ], Query::parse($lastRequest->getUri()->getQuery()));

        $contextSource = $this->storeContext->getSource();
        static::assertInstanceOf(AdminApiSource::class, $contextSource);

        static::assertEquals([
            'shopwareId' => 'shopwareId',
            'password' => 'password',
            'shopwareUserId' => $contextSource->getUserId(),
        ], \json_decode($lastRequest->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR));

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
    }

    public function testItRequestsUpdatesForLoggedInUser(): void
    {
        $pluginList = new ExtensionCollection();
        $pluginList->add((new ExtensionStruct())->assign([
            'name' => 'TestExtension',
            'version' => '1.0.0',
        ]));

        $this->getRequestHandler()->append(new Response(200, [], \json_encode([
            'data' => [],
        ], \JSON_THROW_ON_ERROR)));

        $updateList = $this->storeClient->getExtensionUpdateList($pluginList, $this->storeContext);

        static::assertEquals([], $updateList);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);

        static::assertEquals(
            $this->getStoreTokenFromContext($this->storeContext),
            $lastRequest->getHeader('X-Shopware-Platform-Token')[0],
        );
    }

    public function testItRequestsUpdateForNotLoggedInUser(): void
    {
        $contextSource = $this->storeContext->getSource();
        static::assertInstanceOf(AdminApiSource::class, $contextSource);

        $this->getUserRepository()->update([
            [
                'id' => $contextSource->getUserId(),
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
        ], \JSON_THROW_ON_ERROR)));

        $updateList = $this->storeClient->getExtensionUpdateList($pluginList, $this->storeContext);

        static::assertCount(1, $updateList);
        static::assertEquals('TestExtension', $updateList[0]->getName());
        static::assertEquals('1.1.0', $updateList[0]->getVersion());

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);

        static::assertFalse($lastRequest->hasHeader('X-Shopware-Platform-Token'));
    }

    public function testItReturnsUserInfo(): void
    {
        $userInfo = [
            'name' => 'John Doe',
            'email' => 'john.doe@shopware.com',
            'avatarUrl' => 'https://avatar.shopware.com/john-doe.png',
        ];

        $this->getRequestHandler()->append(new Response(200, [], \json_encode($userInfo, \JSON_THROW_ON_ERROR)));

        $returnedUserInfo = $this->storeClient->userInfo($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);

        static::assertEquals('/swplatform/userinfo', $lastRequest->getUri()->getPath());
        static::assertEquals('GET', $lastRequest->getMethod());
        static::assertEquals($userInfo, $returnedUserInfo);
    }

    public function testMissingConnectionBecauseYouAreInGermanCellularInternet(): void
    {
        $this->getRequestHandler()->append(new ConnectException(
            'cURL error 7: Failed to connect to api.shopware.com port 443 after 4102 ms: Network is unreachable (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.shopware.com/swplatform/pluginupdates?shopwareVersion=6.4.12.0&language=de-DE&domain=',
            $this->createMock(RequestInterface::class)
        ));

        $pluginList = new ExtensionCollection();
        $pluginList->add((new ExtensionStruct())->assign([
            'name' => 'TestExtension',
            'version' => '1.0.0',
        ]));

        $returnedUserInfo = $this->storeClient->getExtensionUpdateList($pluginList, $this->storeContext);

        static::assertSame([], $returnedUserInfo);
    }
}
