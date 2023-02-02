<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminExtensionApiController;
use Shopware\Administration\Controller\Exception\AppByNameNotFoundException;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Manifest\Exception\UnallowedHostException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AdminExtensionApiControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const EXISTING_APP_NAME = 'existingappname';
    private const NONE_EXISTING_APP_NAME = 'noneexistingappname';
    private const APP_URI = 'https://app.infra?location-id=settings-item&privileges=%7B%22read%22%3A%5B%22custom_entity_store%22%5D%2C%22create%22%3A%5B%22custom_entity_store%22%5D%2C%22update%22%3A%5B%22custom_entity_store%22%5D%2C%22delete%22%3A%5B%22custom_entity_store%22%5D%7D';

    private Context $context;

    private AdminExtensionApiController $adminExtensionApiController;

    private EntityRepositoryInterface $appRepository;

    /**
     * @var Executor|MockObject
     */
    private $executor;

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $this->appRepository = $container->get('app.repository');
        $this->executor = $this->createMock(Executor::class);
        $this->context = Context::createDefaultContext();

        $this->adminExtensionApiController = new AdminExtensionApiController(
            $this->executor,
            $container->get(ShopIdProvider::class),
            $this->appRepository,
            $container->get(QuerySigner::class)
        );
    }

    /**
     * @dataProvider providerRunAction
     */
    public function testRunAction(string $appName, ?string $targetUrl = null, ?array $hosts = []): void
    {
        $this->appRepository->create([
            [
                'name' => self::EXISTING_APP_NAME,
                'path' => \sprintf('custom/apps/%s', self::EXISTING_APP_NAME),
                'active' => true,
                'configurable' => false,
                'version' => '0.0.1',
                'label' => 'PHPUnit',
                'appSecret' => 'PHPUnit',
                'integration' => [
                    'label' => 'PHPUnit',
                    'accessKey' => 'foo',
                    'secretAccessKey' => 'bar',
                ],
                'aclRole' => [
                    'name' => self::EXISTING_APP_NAME,
                    'privileges' => [],
                ],
                'allowedHosts' => $hosts,
            ],
        ], $this->context);

        $requestDataBag = new RequestDataBag([
            'appName' => $appName,
            'entity' => 'customer',
            'action' => 'PHPUnit',
            'ids' => [],
        ]);

        if ($targetUrl) {
            $requestDataBag->set('url', $targetUrl);
        }

        $appExists = $appName === self::EXISTING_APP_NAME;
        if (!$appExists) {
            $this->expectException(AppByNameNotFoundException::class);
            $this->expectExceptionMessage(\sprintf('The provided name %s is invalid and no app could be found.', $appName));

            $this->adminExtensionApiController->runAction($requestDataBag, $this->context);

            return;
        }

        if (empty($hosts)) {
            $this->expectException(UnallowedHostException::class);
        } else {
            $this->executor->expects(static::once())->method('execute')->with(static::callback(static function (AppAction $action) use ($targetUrl) {
                return $action->getTargetUrl() === $targetUrl;
            }))->willReturn(new Response());
        }

        $response = $this->adminExtensionApiController->runAction($requestDataBag, $this->context);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function providerRunAction(): array
    {
        return [
            [
                self::NONE_EXISTING_APP_NAME,
            ],
            [
                self::EXISTING_APP_NAME,
                'https://example.com',
            ],
            [
                self::EXISTING_APP_NAME,
                'https://example.com',
                [
                    'example.com',
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerSignUri
     */
    public function testSignUri(RequestDataBag $requestDataBag, bool $expectAppNotFoundError): void
    {
        $this->appRepository->create([
            [
                'name' => self::EXISTING_APP_NAME,
                'path' => \sprintf('custom/apps/%s', self::EXISTING_APP_NAME),
                'active' => true,
                'configurable' => false,
                'version' => '0.0.1',
                'label' => 'PHPUnit',
                'appSecret' => 'PHPUnit',
                'integration' => [
                    'label' => 'PHPUnit',
                    'accessKey' => 'foo',
                    'secretAccessKey' => 'bar',
                ],
                'aclRole' => [
                    'name' => self::EXISTING_APP_NAME,
                    'privileges' => [],
                ],
            ],
        ], $this->context);

        if ($expectAppNotFoundError) {
            $this->expectException(AppByNameNotFoundException::class);
        }

        $response = $this->adminExtensionApiController->signUri($requestDataBag, $this->context);

        if ($expectAppNotFoundError) {
            return;
        }

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $data = \json_decode($content, true);
        static::assertIsArray($data);
        static::assertArrayHasKey('uri', $data);
        static::assertStringStartsWith(self::APP_URI, $data['uri']);
        static::assertStringContainsString('shop-id=', $data['uri']);
        static::assertStringContainsString('shop-url=', $data['uri']);
        static::assertStringContainsString('timestamp=', $data['uri']);
        static::assertStringContainsString('sw-version=', $data['uri']);
        static::assertStringContainsString('sw-context-language=', $data['uri']);
        static::assertStringContainsString('sw-user-language=', $data['uri']);
        static::assertStringContainsString('shopware-shop-signature=', $data['uri']);
    }

    public function providerSignUri(): array
    {
        return [
            [
                new RequestDataBag([
                    'appName' => self::NONE_EXISTING_APP_NAME,
                    'uri' => self::APP_URI,
                ]),
                true,
            ],
            [
                new RequestDataBag([
                    'appName' => self::EXISTING_APP_NAME,
                    'uri' => self::APP_URI,
                ]),
                false,
            ],
        ];
    }
}
