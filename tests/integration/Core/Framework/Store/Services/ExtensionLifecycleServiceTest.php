<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Exception\ExtensionThemeStillInUseException;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *
 * @group skip-paratest
 */
class ExtensionLifecycleServiceTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private ExtensionLifecycleService $lifecycleService;

    private EntityRepository $appRepository;

    private ?EntityRepository $themeRepository;

    private EntityRepository $salesChannelRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->lifecycleService = $this->getContainer()->get(ExtensionLifecycleService::class);

        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->themeRepository = $this->getContainer()->get('theme.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
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

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);

        $testApp = $apps->first();

        static::assertNotNull($testApp);
        static::assertEquals('TestApp', $testApp->getName());
        static::assertFalse($testApp->isActive());
    }

    public function testUninstallWithInvalidNameWithoutMajorFlag(): void
    {
        Feature::skipTestIfActive('V6_6_0_0', $this);

        $this->lifecycleService->uninstall('app', 'notExisting', false, $this->context);
    }

    public function testUninstallWithInvalidNameWithMajorFlag(): void
    {
        Feature::skipTestIfInActive('V6_6_0_0', $this);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Use StoreException::extensionNotFoundFromTechnicalName instead.');
        $this->lifecycleService->uninstall('app', 'notExisting', false, $this->context);
    }

    public function testInstallAppNotExistingWithoutMajorFlag(): void
    {
        Feature::skipTestIfActive('V6_6_0_0', $this);

        static::expectException(ExtensionInstallException::class);
        $this->lifecycleService->install('app', 'notExisting', $this->context);
    }

    public function testInstallAppNotExistingWithMajorFlagActivated(): void
    {
        Feature::skipTestIfInActive('V6_6_0_0', $this);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Use StoreException::extensionInstallException instead.');
        $this->lifecycleService->install('app', 'notExisting', $this->context);
    }

    public function testRemoveExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $this->lifecycleService->uninstall('app', 'TestApp', false, $this->context);
        $this->lifecycleService->remove('app', 'TestApp', $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(0, $apps);
    }

    public function testActivateExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $this->lifecycleService->activate('app', 'TestApp', $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);

        $testApp = $apps->first();

        static::assertNotNull($testApp);
        static::assertEquals('TestApp', $testApp->getName());
        static::assertTrue($testApp->isActive());
    }

    public function testDeactivateExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        $this->lifecycleService->activate('app', 'TestApp', $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        $testApp = $apps->first();

        static::assertNotNull($testApp);
        static::assertTrue($testApp->isActive());

        $this->lifecycleService->deactivate('app', 'TestApp', $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);

        $testApp = $apps->first();

        static::assertNotNull($testApp);
        static::assertEquals('TestApp', $testApp->getName());
        static::assertFalse($testApp->isActive());
    }

    public function testUpdateExtensionNotExistingWithoutMajorFlag(): void
    {
        Feature::skipTestIfActive('V6_6_0_0', $this);

        static::expectException(ExtensionInstallException::class);
        $this->lifecycleService->update('app', 'foo', false, $this->context);
    }

    public function testUpdateExtensionNotExistingWithMajorFlag(): void
    {
        Feature::skipTestIfInActive('V6_6_0_0', $this);

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Use StoreException::extensionInstallException instead.');
        $this->lifecycleService->update('app', 'foo', false, $this->context);
    }

    public function testUpdateExtensionNotInstalledWithoutMajorFlag(): void
    {
        Feature::skipTestIfActive('V6_6_0_0', $this);

        $this->installApp(__DIR__ . '/../_fixtures/TestApp', false);
        static::expectException(ExtensionNotFoundException::class);
        $this->lifecycleService->update('app', 'TestApp', false, $this->context);
    }

    public function testUpdateExtensionNotInstalledWithMajorFlag(): void
    {
        Feature::skipTestIfInActive('V6_6_0_0', $this);

        $this->installApp(__DIR__ . '/../_fixtures/TestApp', false);
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Use StoreException::extensionNotFoundFromTechnicalName instead.');
        $this->lifecycleService->update('app', 'TestApp', false, $this->context);
    }

    public function testUpdateExtension(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        $testApp = $apps->first();

        static::assertNotNull($testApp);
        static::assertSame('1.0.0', $testApp->getVersion());

        $appManifestPath = $this->getContainer()->getParameter('kernel.app_dir') . '/TestApp/manifest.xml';

        $appManifest = file_get_contents($appManifestPath);
        static::assertIsString($appManifest);

        file_put_contents($appManifestPath, str_replace('1.0.0', '1.0.1', $appManifest));

        $this->lifecycleService->update('app', 'TestApp', false, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        $testApp = $apps->first();

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

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        $testApp = $apps->first();

        static::assertNotNull($testApp);

        /** @var ThemeEntity|null $theme */
        $theme = $themeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'TestAppTheme')),
            $this->context
        )->first();

        $defaultSalesChannelId = $this->salesChannelRepository->searchIds(new Criteria(), $this->context)
            ->firstId();

        static::assertNotNull($theme);
        static::assertNotNull($defaultSalesChannelId);

        $this->salesChannelRepository->update([[
            'id' => $defaultSalesChannelId,
            'themes' => [
                ['id' => $theme->getId()],
            ],
        ]], $this->context);

        static::expectException(ExtensionThemeStillInUseException::class);
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

        /** @var ThemeEntity|null $theme */
        $theme = $themeRepo->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'TestAppTheme')),
            $this->context
        )->first();

        static::assertNotNull($theme);

        $childThemeId = Uuid::randomHex();
        $themeRepo->create([[
            'id' => $childThemeId,
            'name' => 'SwagTest',
            'author' => 'Shopware',
            'active' => true,
            'parentThemeId' => $theme->getId(),
        ]], $this->context);

        $defaultSalesChannelId = $this->salesChannelRepository->searchIds(new Criteria(), $this->context)
            ->firstId();

        $this->salesChannelRepository->update([[
            'id' => $defaultSalesChannelId,
            'themes' => [
                ['id' => $childThemeId],
            ],
        ]], $this->context);

        static::expectException(ExtensionThemeStillInUseException::class);
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

        /** @var ThemeEntity|null $theme */
        $theme = $themeRepo->search($themeCriteria, $this->context)->first();

        static::assertNotNull($theme);

        $salesChannels = $theme->getSalesChannels();

        static::assertNotNull($salesChannels);
        static::assertEquals(0, $salesChannels->count());

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

        $oldName = $this->getContainer()->getParameter('shopware.app_dir') . '/TestAppTheme';
        $newName = $this->getContainer()->getParameter('shopware.app_dir') . '/some-random-folder-name';

        rename($oldName, $newName);

        $this->lifecycleService->remove('app', 'TestAppTheme', Context::createDefaultContext());

        static::assertFileDoesNotExist($newName);
    }
}
