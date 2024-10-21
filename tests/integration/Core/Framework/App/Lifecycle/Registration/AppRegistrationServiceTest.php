<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\App\TestAppServer;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class AppRegistrationServiceTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private AppRegistrationService $registrator;

    private string $shopUrl;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private ShopIdProvider $shopIdProvider;

    protected function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->registrator = $this->getContainer()->get(AppRegistrationService::class);
        $this->shopUrl = (string) EnvironmentHelper::getVariable('APP_URL');
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
    }

    public function testRegisterPrivateApp(): void
    {
        $id = Uuid::randomHex();
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $this->createApp($id);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $appSecret = 'dont_tell';
        $appResponseBody = $this->buildAppResponse($manifest, $appSecret);

        $this->appendNewResponse(new Response(200, [], $appResponseBody));
        $this->appendNewResponse(new Response(200, []));

        $this->registrator->registerApp($manifest, $id, $secretAccessKey, Context::createDefaultContext());

        $registrationRequest = $this->getPastRequest(0);

        $setup = $manifest->getSetup();
        static::assertNotNull($setup);

        $uriWithoutQuery = $registrationRequest->getUri()->withQuery('');
        static::assertSame($setup->getRegistrationUrl(), (string) $uriWithoutQuery);
        static::assertNotEmpty($registrationRequest->getHeaderLine('sw-version'));
        static::assertNotEmpty($registrationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($registrationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));

        $secret = $setup->getSecret();
        static::assertNotNull($secret);

        $this->assertRequestIsSigned($registrationRequest, $secret);

        $app = $this->fetchApp($id);

        static::assertSame(TestAppServer::APP_SECRET, $app->getAppSecret());

        static::assertSame(2, $this->getRequestCount());

        $confirmationReq = $this->getPastRequest(1);
        static::assertSame('POST', $confirmationReq->getMethod());

        $postBody = \json_decode($confirmationReq->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($secretAccessKey, $postBody['secretKey']);

        $integration = $app->getIntegration();
        static::assertNotNull($integration);
        static::assertSame($integration->getAccessKey(), $postBody['apiKey']);

        static::assertSame($_SERVER['APP_URL'], $postBody['shopUrl']);
        static::assertSame($this->shopIdProvider->getShopId(), $postBody['shopId']);

        $json = \json_encode($postBody, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        static::assertSame(
            \hash_hmac('sha256', $json, $appSecret),
            $confirmationReq->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($confirmationReq->getHeaderLine('sw-version'));
        static::assertNotEmpty($registrationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($registrationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testRegistrationConfirmFails(): void
    {
        $id = Uuid::randomHex();
        $this->createApp($id);
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $appSecret = 'dont_tell';
        $appResponseBody = $this->buildAppResponse($manifest, $appSecret);

        $this->appendNewResponse(new Response(200, [], $appResponseBody));
        $this->appendNewResponse(new Response(500, []));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, $id, $secretAccessKey, Context::createDefaultContext());
    }

    public function testRegistrationFailsWithWrongProof(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $this->appendNewResponse(new Response(200, [], '{"proof": "wrong proof"}'));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());
    }

    public function testRegistrationFailsWithWrongProofAsArray(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $this->appendNewResponse(new Response(200, [], '{"proof": ["wrong proof"]}'));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());
    }

    public function testRegistrationFailsWithoutProof(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $this->appendNewResponse(new Response(200, [], '{}'));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());
    }

    public function testRegistrationFailsIfRegistrationRequestIsNotHTTP200(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $appSecret = 'dont_tell';
        $appResponseBody = $this->buildAppResponse($manifest, $appSecret);

        $this->appendNewResponse(new Response(500, [], $appResponseBody));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());
    }

    public function testRegistrationFailsIfAppUrlChangeWasDetected(): void
    {
        $id = Uuid::randomHex();
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $this->createApp($id);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $appSecret = 'dont_tell';
        $shopId = Uuid::randomHex();
        $appResponseBody = $this->buildAppResponse($manifest, $appSecret, $shopId);

        $this->appendNewResponse(new Response(200, [], $appResponseBody));

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => $shopId,
        ]);

        $shopIdProviderMock = $this->createMock(ShopIdProvider::class);
        $shopIdProviderMock->expects(static::once())
            ->method('getShopId')
            ->willReturn($shopId);

        $handshakeFactory = new HandshakeFactory(
            $this->shopUrl,
            $shopIdProviderMock,
            $this->getContainer()->get(StoreClient::class),
            Kernel::SHOPWARE_FALLBACK_VERSION
        );

        $shopIdMock = $this->createMock(ShopIdProvider::class);
        $shopIdMock->expects(static::once())
            ->method('getShopId')
            ->willThrowException(new AppUrlChangeDetectedException('https://test.com', 'https://new.com', $shopId));

        $registrator = new AppRegistrationService(
            $handshakeFactory,
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->getContainer()->get('app.repository'),
            $this->shopUrl,
            $shopIdMock,
            Kernel::SHOPWARE_FALLBACK_VERSION
        );

        static::expectException(AppRegistrationException::class);
        $registrator->registerApp($manifest, $id, $secretAccessKey, Context::createDefaultContext());
    }

    // currently not implemented
    public function testRegisterStoreApp(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        static::expectException(\RuntimeException::class);
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());

        $registrationRequest = $this->getPastRequest(0);
        $confirmationRequest = $this->getPastRequest(1);
        static::assertNotEmpty($registrationRequest->getHeaderLine('sw-version'));
        static::assertNotEmpty($registrationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($registrationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
        static::assertNotEmpty($confirmationRequest->getHeaderLine('sw-version'));
        static::assertNotEmpty($confirmationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($confirmationRequest->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testDoesNotRegisterIfNoSetupElementIsProvided(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/no-setup/manifest.xml');

        // mockHandler would throw if it tries to make a registration request
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());
    }

    public function testRegistrationFailsWithError(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $this->appendNewResponse(new Response(500, [], '{"error": "Shop url is not met"}'));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, '', '', Context::createDefaultContext());
    }

    public function testConfirmRegistrationFailsWithError(): void
    {
        $id = Uuid::randomHex();
        $this->createApp($id);
        $secretAccessKey = AccessKeyHelper::generateSecretAccessKey();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/minimal/manifest.xml');

        $appSecret = 'dont_tell';
        $appResponseBody = $this->buildAppResponse($manifest, $appSecret);

        $this->appendNewResponse(new Response(200, [], $appResponseBody));
        $this->appendNewResponse(new Response(500, [], '{"error": "Shop url is not met"}'));

        static::expectException(AppRegistrationException::class);
        $this->registrator->registerApp($manifest, $id, $secretAccessKey, Context::createDefaultContext());
    }

    private function createApp(string $id): void
    {
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'SwagApp',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'testtoken',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'testkey',
                'secretAccessKey' => 'test',
            ],
            'customFieldSets' => [
                [
                    'name' => 'test',
                ],
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'permissions' => [
                'product' => ['update'],
            ],
        ]);

        $permissionPersister->updatePrivileges($permissions, $roleId);
    }

    private function buildAppResponse(Manifest $manifest, string $appSecret, ?string $shopId = null): string
    {
        if (!$shopId) {
            $shopId = $this->shopIdProvider->getShopId();
        }

        $setup = $manifest->getSetup();
        static::assertNotNull($setup);
        $secret = $setup->getSecret();
        static::assertNotNull($secret);

        $proof = \hash_hmac(
            'sha256',
            $shopId . $this->shopUrl . $manifest->getMetadata()->getName(),
            $secret
        );

        $confirmationUrl = 'https://my-app.com/confirm';
        $appResponseBody = \json_encode(['proof' => $proof, 'secret' => $appSecret, 'confirmation_url' => $confirmationUrl], \JSON_THROW_ON_ERROR);

        static::assertNotFalse($appResponseBody);

        return $appResponseBody;
    }

    private function assertRequestIsSigned(RequestInterface $registrationRequest, string $secret): void
    {
        static::assertSame(
            hash_hmac('sha256', $registrationRequest->getUri()->getQuery(), $secret),
            $registrationRequest->getHeaderLine('shopware-app-signature')
        );
    }

    private function fetchApp(string $id): AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('integration');
        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }
}
