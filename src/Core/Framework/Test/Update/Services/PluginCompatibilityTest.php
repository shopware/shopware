<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Services;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class PluginCompatibilityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ExtensionBehaviour;
    use StoreClientBehaviour;

    private ExtensionLifecycleService $lifecycleService;

    private PluginCompatibility $pluginCompatibility;

    private EntityRepositoryInterface $appRepository;

    private EntityRepositoryInterface $pluginRepository;

    private EntityRepositoryInterface $themeRepository;

    private EntityRepositoryInterface $salesChannelRepository;

    private ApiClient $apiClient;

    private Context $context;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        $this->pluginCompatibility = $this->getContainer()->get(PluginCompatibility::class);
        $this->apiClient = $this->getContainer()->get(ApiClient::class);

        $requestStack = $this->getContainer()->get('request_stack');
        $requestStack->push(new Request());

        $this->lifecycleService = $this->getContainer()->get(ExtensionLifecycleService::class);
        $userId = Uuid::randomHex();
        $storeToken = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => 'foo@bar.com',
                'storeToken' => $storeToken,
            ],
        ];
        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);
        $this->context = Context::createDefaultContext($source);

        // Install extensions
        $appContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $this->installApp(__DIR__ . '/../_fixtures/TestApp', false);
        $this->lifecycleService->install('app', 'TestApp', $appContext);
        $this->lifecycleService->activate('app', 'TestApp', $appContext);
    }

    public function tearDown(): void
    {
        $this->removeApp(__DIR__ . '/../_fixtures/TestApp');
    }

    public function testGetExtensionFutureCompatibility(): void
    {
        $extensionResponse = file_get_contents(__DIR__ . './../_fixtures/responses/extension-yellow.json');
        $this->getRequestHandler()->append(new Response(
            200,
            [],
            $extensionResponse,
        ));

        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $getExtensionCompatibilities = $this->pluginCompatibility->getExtensionCompatibilities($version, $this->context);

        static::assertSame('updatableFuture', $getExtensionCompatibilities[0]['statusName']);
        static::assertSame('yellow', $getExtensionCompatibilities[0]['statusColor']);
    }

    public function testGetExtensionCompatibility(): void
    {
        $extensionResponse = file_get_contents(__DIR__ . './../_fixtures/responses/extension-green.json');
        $this->getRequestHandler()->append(new Response(
            200,
            [],
            $extensionResponse,
        ));

        $version = new Version();
        $version->assign([
            'version' => '6.3.5.0',
        ]);

        $getExtensionCompatibilities = $this->pluginCompatibility->getExtensionCompatibilities($version, $this->context);

        static::assertSame('compatible', $getExtensionCompatibilities[0]['statusName']);
        static::assertSame('success', $getExtensionCompatibilities[0]['statusVariant']);
    }

    public function testGetExtensionNotCompatibility(): void
    {
        $extensionResponse = file_get_contents(__DIR__ . './../_fixtures/responses/extension-red.json');
        $this->getRequestHandler()->append(new Response(
            200,
            [],
            $extensionResponse,
        ));

        $version = new Version();
        $version->assign([
            'version' => '6.3.5.0',
        ]);

        $getExtensionCompatibilities = $this->pluginCompatibility->getExtensionCompatibilities($version, $this->context);

        static::assertSame('notCompatible', $getExtensionCompatibilities[0]['statusName']);
        static::assertSame('error', $getExtensionCompatibilities[0]['statusVariant']);
    }
}
