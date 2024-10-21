<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Facade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptAppInformation;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\Script\Execution\SalesChannelTestHook;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('services-settings')]
class SystemConfigFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private SystemConfigService $systemConfigService;

    private SystemConfigFacadeHookFactory $factory;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->factory = $this->getContainer()->get(SystemConfigFacadeHookFactory::class);
    }

    #[DataProvider('getWithoutAppCases')]
    public function testGetForScriptWithoutApp(Hook $hook, ?string $salesChannelId, string $result): void
    {
        $this->systemConfigService->set('test.value', 'generic');
        $this->systemConfigService->set('test.value', 'specific', TestDefaults::SALES_CHANNEL);

        $facade = $this->factory->factory(
            $hook,
            new Script('test', '', new \DateTimeImmutable())
        );

        static::assertEquals($result, $facade->get('test.value', $salesChannelId));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function getWithoutAppCases(): array
    {
        $salesChannelContext = Generator::createSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setId(TestDefaults::SALES_CHANNEL);

        return [
            'simpleGet' => [
                new TestHook('test', Context::createDefaultContext()),
                null,
                'generic',
            ],
            'salesChannelSpecificGet' => [
                new TestHook('test', Context::createDefaultContext()),
                TestDefaults::SALES_CHANNEL,
                'specific',
            ],
            'itUsesSalesChannelFromSalesChannelContextPerDefault' => [
                new SalesChannelTestHook('test', $salesChannelContext),
                null,
                'specific',
            ],
            'overrideForSalesChannelContext' => [
                new SalesChannelTestHook('test', $salesChannelContext),
                Uuid::randomHex(), // as the value for this salesChannel does not exist it falls back to the generic one
                'generic',
            ],
        ];
    }

    public function testGetThrowsExceptionForAppWithoutPermission(): void
    {
        $this->systemConfigService->set('test.value', 'generic');

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutSystemConfigPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::expectException(MissingPrivilegeException::class);
        $facade->get('test.value');
    }

    public function testGetForAppWithout(): void
    {
        $this->systemConfigService->set('test.value', 'generic');

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withSystemConfigPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::assertEquals('generic', $facade->get('test.value'));
    }

    public function testGetAppConfigForAppWithoutPermission(): void
    {
        $this->systemConfigService->set('withoutSystemConfigPermission.config.testValue', 'test');

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withoutSystemConfigPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::assertEquals('test', $facade->app('testValue'));
    }

    public function testGetAppConfigForApp(): void
    {
        $this->systemConfigService->set('withSystemConfigPermission.config.testValue', 'test');

        $appInfo = $this->installApp(__DIR__ . '/_fixtures/apps/withSystemConfigPermission');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable(), $appInfo)
        );

        static::assertEquals('test', $facade->app('testValue'));
    }

    public function testGetAppConfigThrowsWithoutApp(): void
    {
        $this->systemConfigService->set('withSystemConfigPermission.config.testValue', 'test');

        $facade = $this->factory->factory(
            new TestHook('test', Context::createDefaultContext()),
            new Script('test', '', new \DateTimeImmutable())
        );

        static::expectException(\BadMethodCallException::class);
        $facade->app('testValue');
    }

    public function testSystemConfigIntegrationTest(): void
    {
        $this->systemConfigService->set('core.listing.productsPerPage', 'system_config');
        $this->systemConfigService->set('systemConfigExample.config.app_config', 'app_config');

        $this->installApp(__DIR__ . '/_fixtures/apps/systemConfigExample');

        $page = new ArrayStruct();
        $hook = new TestHook(
            'test-config',
            Context::createDefaultContext(),
            [
                'page' => $page,
            ],
            [
                SystemConfigFacadeHookFactory::class,
            ]
        );

        $this->getContainer()->get(ScriptExecutor::class)->execute($hook);

        static::assertTrue($page->hasExtension('systemConfigExtension'));
        $extension = $page->getExtension('systemConfigExtension');
        static::assertInstanceOf(ArrayStruct::class, $extension);

        static::assertEquals('system_config', $extension->get('systemConfig'));
        static::assertEquals('app_config', $extension->get('appConfig'));
    }

    private function installApp(string $appDir): ScriptAppInformation
    {
        $this->loadAppsFromDir($appDir);

        /** @var AppEntity $app */
        $app = $this->getContainer()->get('app.repository')->search(new Criteria(), Context::createDefaultContext())->first();

        return new ScriptAppInformation(
            $app->getId(),
            $app->getName(),
            $app->getIntegrationId()
        );
    }
}
