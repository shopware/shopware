<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Store\Services\ExtensionLoader;
use Shopware\Core\Framework\Store\Struct\BinaryCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\ImageCollection;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;
use Shopware\Core\Framework\Store\Struct\PermissionStruct;
use Shopware\Core\Framework\Store\Struct\VariantCollection;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 *
 * @group skip-paratest
 */
class ExtensionLoaderTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private ExtensionLoader $extensionLoader;

    protected function setUp(): void
    {
        $this->extensionLoader = $this->getContainer()->get(ExtensionLoader::class);

        $this->registerPlugin(__DIR__ . '/../_fixtures/AppStoreTestPlugin');
        $this->installApp(__DIR__ . '/../_fixtures/TestApp');
    }

    protected function tearDown(): void
    {
        $this->removePlugin(__DIR__ . '/../_fixtures/AppStoreTestPlugin');
        $this->removeApp(__DIR__ . '/../_fixtures/TestApp');
    }

    public function testAppNotInstalledDetectedAsTheme(): void
    {
        $this->installApp(__DIR__ . '/../_fixtures/TestAppTheme', false);
        $extensions = $this->extensionLoader->loadFromAppCollection(
            Context::createDefaultContext(),
            new AppCollection([])
        );

        /** @var ExtensionStruct $extension */
        $extension = $extensions->get('TestAppTheme');
        static::assertTrue($extension->isTheme());
        $this->removeApp(__DIR__ . '/../_fixtures/TestAppTheme');
    }

    public function testLocalUpdateShouldSetLatestVersion(): void
    {
        $appManifestPath = $this->getContainer()->getParameter('kernel.app_dir') . '/TestApp/manifest.xml';
        $appManifestXml = file_get_contents($appManifestPath);
        static::assertIsString($appManifestXml, 'Could not read manifest.xml file');
        file_put_contents($appManifestPath, str_replace('1.0.0', '1.0.1', $appManifestXml));

        $extensions = $this->extensionLoader->loadFromAppCollection(
            Context::createDefaultContext(),
            new AppCollection([$this->getInstalledApp()])
        );

        /** @var ExtensionStruct $extension */
        $extension = $extensions->get('TestApp');
        static::assertSame('1.0.0', $extension->getVersion());
        static::assertSame('1.0.1', $extension->getLatestVersion());
    }

    public function testItLoadsExtensionFromResponseLikeArray(): void
    {
        $listingResponse = $this->getDetailResponseFixture();

        $extension = $this->extensionLoader->loadFromArray(
            Context::createDefaultContext(),
            $listingResponse
        );

        static::assertNull($extension->getLocalId());
        static::assertNull($extension->getLicense());
        static::assertNull($extension->getVersion());
        static::assertEquals($listingResponse['name'], $extension->getName());
        static::assertEquals($listingResponse['label'], $extension->getLabel());

        static::assertInstanceOf(VariantCollection::class, $extension->getVariants());
        static::assertInstanceOf(ImageCollection::class, $extension->getImages());
        static::assertInstanceOf(BinaryCollection::class, $extension->getBinaries());
    }

    public function testLoadsExtensionsFromListingArray(): void
    {
        $listingResponse = $this->getListingResponseFixture();

        $extensions = $this->extensionLoader->loadFromListingArray(
            Context::createDefaultContext(),
            $listingResponse
        );

        static::assertInstanceOf(ExtensionCollection::class, $extensions);
        static::assertCount(2, $extensions);
    }

    public function testItLoadsExtensionsFromPlugins(): void
    {
        $this->getContainer()->get(PluginService::class)->refreshPlugins(Context::createDefaultContext(), new NullIO());

        /** @var PluginCollection $plugins */
        $plugins = $this->getContainer()->get('plugin.repository')->search(new Criteria(), Context::createDefaultContext())->getEntities();

        $extensions = $this->extensionLoader->loadFromPluginCollection(Context::createDefaultContext(), $plugins);

        /** @var ExtensionStruct $extension */
        $extension = $extensions->get('AppStoreTestPlugin');

        static::assertNotNull($extension);
        static::assertEquals('AppStoreTestPlugin', $extension->getName());
    }

    public function testUpgradeAtMapsToUpdatedAtInStruct(): void
    {
        $this->getContainer()->get(PluginService::class)->refreshPlugins(Context::createDefaultContext(), new NullIO());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'AppStoreTestPlugin'));

        $firstPluginId = $this->getContainer()->get('plugin.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();

        $time = new \DateTime();

        /** @var EntityRepository $pluginRepository */
        $pluginRepository = $this->getContainer()->get('plugin.repository');
        $pluginRepository->update([
            [
                'id' => $firstPluginId,
                'upgradedAt' => $time,
            ],
        ], Context::createDefaultContext());

        $firstPlugin = $this->getContainer()->get('plugin.repository')->search($criteria, Context::createDefaultContext())->first();

        $extensions = $this->extensionLoader->loadFromPluginCollection(Context::createDefaultContext(), new PluginCollection([$firstPlugin]));

        static::assertSame($time->getTimestamp(), $extensions->first()->getUpdatedAt()->getTimestamp());
    }

    public function testItLoadsExtensionsFromAppsCollection(): void
    {
        $installedApp = $this->getInstalledApp();

        $extensions = $this->extensionLoader->loadFromAppCollection(
            Context::createDefaultContext(),
            new AppCollection([$installedApp])
        );

        static::assertInstanceOf(ExtensionCollection::class, $extensions);
        static::assertEquals([
            'German',
            'British English',
        ], $extensions->first()->getLanguages());

        static::assertSame($installedApp->getUpdatedAt(), $extensions->first()->getUpdatedAt());
        static::assertEquals(new PermissionCollection([
            PermissionStruct::fromArray(['entity' => 'product', 'operation' => 'create']),
            PermissionStruct::fromArray(['entity' => 'product', 'operation' => 'read']),
            PermissionStruct::fromArray(['entity' => 'additional_privileges', 'operation' => 'additional:privilege']),
        ]), $extensions->first()->getPermissions());

        foreach ($extensions as $extension) {
            static::assertEquals(ExtensionStruct::EXTENSION_TYPE_APP, $extension->getType());
        }
    }

    private function getInstalledApp(): AppEntity
    {
        $appRepository = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('translations');

        $app = $appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($app, 'Installed app not found');

        return $app;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDetailResponseFixture(): array
    {
        $content = file_get_contents(__DIR__ . '/../_fixtures/responses/extension-detail.json');
        static::assertIsString($content, 'Could not read extension-detail.json file');

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getListingResponseFixture(): array
    {
        $content = file_get_contents(__DIR__ . '/../_fixtures/responses/extension-listing.json');
        static::assertIsString($content, 'Could not read extension-listing.json file');

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
