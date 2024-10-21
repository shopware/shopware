<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\ThemeCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Group('skip-paratest')]
#[Package('checkout')]
class ExtensionLifecycleServiceTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private AbstractExtensionLifecycle $lifecycleService;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    /**
     * @var EntityRepository<ThemeCollection>|null
     *
     * @phpstan-ignore property.unusedType (can be null in a test, where the storefront is not installed)
     */
    private ?EntityRepository $themeRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->lifecycleService = static::getContainer()->get(ExtensionLifecycleService::class);

        $this->appRepository = static::getContainer()->get('app.repository');
        $this->themeRepository = static::getContainer()->get('theme.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $this->context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
    }

    protected function tearDown(): void
    {
        $this->removeApp(__DIR__ . '/../_fixtures/TestApp');
        $this->removeApp(__DIR__ . '/../_fixtures/TestAppTheme');
        $this->removePlugin(__DIR__ . '/../_fixtures/AppStoreTestPlugin');
    }

    public function testInstallExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp', false);

        $this->lifecycleService->install('app', 'TestApp', $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);

        $testApp = $apps->first();
        static::assertNotNull($testApp);
        static::assertSame('TestApp', $testApp->getName());
        static::assertFalse($testApp->isActive());
    }

    public function testUninstallWithInvalidNameWithout(): void
    {
        $this->lifecycleService->uninstall('app', 'notExisting', false, $this->context);
    }

    public function testInstallAppNotExisting(): void
    {
        $this->expectException(StoreException::class);
        $this->expectExceptionMessage('Cannot find app by name notExisting');
        $this->lifecycleService->install('app', 'notExisting', $this->context);
    }

    public function testRemoveExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $this->lifecycleService->uninstall('app', 'TestApp', false, $this->context);
        $this->lifecycleService->remove('app', 'TestApp', $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(0, $apps);
    }

    public function testActivateExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $this->lifecycleService->activate('app', 'TestApp', $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);

        $testApp = $apps->first();
        static::assertNotNull($testApp);
        static::assertSame('TestApp', $testApp->getName());
        static::assertTrue($testApp->isActive());
    }

    public function testDeactivateExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $this->lifecycleService->activate('app', 'TestApp', $this->context);

        $testApp = $this->appRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($testApp);
        static::assertTrue($testApp->isActive());

        $this->lifecycleService->deactivate('app', 'TestApp', $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);

        $testApp = $apps->first();
        static::assertNotNull($testApp);
        static::assertSame('TestApp', $testApp->getName());
        static::assertFalse($testApp->isActive());
    }

    public function testUpdateExtensionNotExisting(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot find extension');
        $this->lifecycleService->update('app', 'foo', false, $this->context);
    }

    public function testUpdateExtensionNotInstalled(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp', false);
        $this->expectException(StoreException::class);
        $this->expectExceptionMessage('Could not find extension with technical name "TestApp"');
        $this->lifecycleService->update('app', 'TestApp', false, $this->context);
    }

    public function testUpdateExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $testApp = $this->appRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($testApp);
        static::assertSame('1.0.0', $testApp->getVersion());

        $appManifestPath = static::getContainer()->getParameter('kernel.app_dir') . '/TestApp/manifest.xml';
        $appManifest = file_get_contents($appManifestPath);
        static::assertIsString($appManifest);
        file_put_contents($appManifestPath, str_replace('1.0.0', '1.0.1', $appManifest));

        $this->lifecycleService->update('app', 'TestApp', false, $this->context);

        $testApp = $this->appRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($testApp);
        static::assertSame('1.0.1', $testApp->getVersion());
    }

    public function testExtensionCanNotBeRemovedIfAThemeIsAssigned(): void
    {
        $themeRepo = $this->themeRepository;
        if (!$themeRepo) {
            static::markTestSkipped('ExtensionLifecycleServiceTest needs storefront to be installed.');
        }

        $this->installApp(__DIR__ . '/../_fixtures/TestAppTheme');
        $this->lifecycleService->activate('app', 'TestAppTheme', $this->context);

        $testApp = $this->appRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($testApp);

        $theme = $themeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'TestAppTheme')),
            $this->context
        )->getEntities()->first();
        static::assertNotNull($theme);

        $defaultSalesChannelId = $this->salesChannelRepository->searchIds(new Criteria(), $this->context)->firstId();
        static::assertNotNull($defaultSalesChannelId);

        $this->salesChannelRepository->update([[
            'id' => $defaultSalesChannelId,
            'themes' => [
                ['id' => $theme->getId()],
            ],
        ]], $this->context);

        $this->expectException(StoreException::class);
        $this->expectExceptionMessage(\sprintf('The extension with id "%s" can not be removed because its theme is still assigned to a sales channel.', $testApp->getId()));
        $this->lifecycleService->uninstall(
            'app',
            $testApp->getName(),
            false,
            $this->context
        );
    }

    public function testExtensionCantBeRemovedIfAChildThemeIsAssigned(): void
    {
        $themeRepo = $this->themeRepository;
        if (!$themeRepo) {
            static::markTestSkipped('ExtensionLifecycleServiceTest needs storefront to be installed.');
        }

        $this->installApp(__DIR__ . '/../_fixtures/TestAppTheme');
        $this->lifecycleService->activate('app', 'TestAppTheme', $this->context);

        $theme = $themeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'TestAppTheme')),
            $this->context
        )->getEntities()->first();
        static::assertNotNull($theme);

        $childThemeId = Uuid::randomHex();
        $themeRepo->create([[
            'id' => $childThemeId,
            'name' => 'SwagTest',
            'author' => 'Shopware',
            'active' => true,
            'parentThemeId' => $theme->getId(),
        ]], $this->context);

        $defaultSalesChannelId = $this->salesChannelRepository->searchIds(new Criteria(), $this->context)->firstId();

        $this->salesChannelRepository->update([[
            'id' => $defaultSalesChannelId,
            'themes' => [
                ['id' => $childThemeId],
            ],
        ]], $this->context);

        $this->expectException(StoreException::class);
        $testApp = $this->appRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($testApp);
        $this->expectExceptionMessage(\sprintf('The extension with id "%s" can not be removed because its theme is still assigned to a sales channel.', $testApp->getId()));
        $this->lifecycleService->uninstall(
            'app',
            'TestAppTheme',
            false,
            $this->context
        );
    }

    public function testExtensionCanBeRemovedIfThemeIsNotAssigned(): void
    {
        $themeRepo = $this->themeRepository;
        if (!$themeRepo) {
            static::markTestSkipped('ExtensionLifecycleServiceTest needs storefront to be installed.');
        }

        $this->installApp(__DIR__ . '/../_fixtures/TestAppTheme');
        $this->lifecycleService->activate('app', 'TestAppTheme', $this->context);

        $themeCriteria = new Criteria();
        $themeCriteria->addFilter(new EqualsFilter('technicalName', 'TestAppTheme'))
            ->addAssociation('salesChannels');

        $theme = $themeRepo->search($themeCriteria, $this->context)->getEntities()->first();
        static::assertNotNull($theme);

        $salesChannels = $theme->getSalesChannels();
        static::assertNotNull($salesChannels);
        static::assertCount(0, $salesChannels);

        $this->lifecycleService->uninstall(
            'type',
            'TestAppTheme',
            false,
            $this->context
        );

        $removedApp = $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'TestAppTheme')),
            $this->context
        )->first();

        static::assertNull($removedApp);
    }

    public function testDeleteAppWithDifferentName(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestAppTheme');

        $oldName = static::getContainer()->getParameter('shopware.app_dir') . '/TestAppTheme';
        $newName = static::getContainer()->getParameter('shopware.app_dir') . '/some-random-folder-name';

        rename($oldName, $newName);

        $this->lifecycleService->remove('app', 'TestAppTheme', Context::createDefaultContext());

        static::assertFileDoesNotExist($newName);
    }
}
