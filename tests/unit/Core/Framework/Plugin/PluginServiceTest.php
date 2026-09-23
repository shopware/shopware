<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\Version\VersionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginService::class)]
class PluginServiceTest extends TestCase
{
    public function testPluginsAddsPlugin(): void
    {
        $pluginFinder = static::createStub(PluginFinder::class);
        $completePackage = $this->getComposerPackage();

        $pluginFromFileSystemStruct = new PluginFromFileSystemStruct();
        $pluginFromFileSystemStruct->assign([
            'baseClass' => 'foo',
            'path' => __DIR__,
            'composerPackage' => $completePackage,
            'managedByComposer' => true,
        ]);

        $pluginFinder
            ->method('findPlugins')
            ->willReturn([
                $pluginFromFileSystemStruct,
            ]);

        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        $pluginService->refreshPlugins(Context::createDefaultContext(), static::createStub(IOInterface::class));

        $upserts = $pluginRepo->upserts;
        static::assertCount(1, $upserts, 'There should be one plugin upserted');
        static::assertArrayHasKey('0', $upserts);

        static::assertCount(1, $upserts['0']);

        $pluginWrite = $upserts['0']['0'];

        static::assertSame('foo', $pluginWrite['name']);
        static::assertSame('foo', $pluginWrite['baseClass']);
        static::assertSame('foo', $pluginWrite['composerName']);
        static::assertSame('1.0.0', $pluginWrite['version']);
    }

    public function testPluginsAliasesGetResolved(): void
    {
        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginFromFileSystemStruct = new PluginFromFileSystemStruct();
        $completePackage = $this->getComposerPackage();

        $package = new CompleteAliasPackage($completePackage, VersionParser::DEFAULT_BRANCH_ALIAS, VersionParser::DEFAULT_BRANCH_ALIAS);

        $pluginFromFileSystemStruct->assign([
            'baseClass' => 'foo',
            'path' => __DIR__,
            'composerPackage' => $package,
            'managedByComposer' => true,
        ]);

        $pluginFinder
            ->method('findPlugins')
            ->willReturn([
                $pluginFromFileSystemStruct,
            ]);

        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        $pluginService->refreshPlugins(Context::createDefaultContext(), static::createStub(IOInterface::class));

        $upserts = $pluginRepo->upserts;
        static::assertCount(1, $upserts, 'There should be one plugin upserted');
        static::assertArrayHasKey('0', $upserts);

        static::assertCount(1, $upserts['0']);

        $pluginWrite = $upserts['0']['0'];

        static::assertSame('foo', $pluginWrite['name']);
        static::assertSame('foo', $pluginWrite['baseClass']);
        static::assertSame('foo', $pluginWrite['composerName']);
        static::assertSame('1.0.0', $pluginWrite['version']);
    }

    public function testGetPluginByName(): void
    {
        $plugin = (new PluginEntity())->assign(['id' => 'foo', 'name' => 'foo']);
        $pluginRepo = new StaticEntityRepository([new PluginCollection([$plugin])]);
        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        static::assertSame($plugin, $pluginService->getPluginByName('foo', Context::createDefaultContext()));
    }

    public function testGetPluginByNameThrowsExceptionWhenPluginDoesNotExist(): void
    {
        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);
        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        $this->expectExceptionObject(PluginException::notFound('foo'));
        $pluginService->getPluginByName('foo', Context::createDefaultContext());
    }

    public function testRefreshPluginsDeletesPluginsThatAreNotFoundOnTheFileSystem(): void
    {
        $plugin = (new PluginEntity())->assign([
            'id' => 'foo',
            'baseClass' => 'Foo\\Plugin',
        ]);
        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginFinder->method('findPlugins')->willReturn([]);
        $pluginRepo = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $this->getPluginService($pluginRepo, $pluginFinder)->refreshPlugins(
            Context::createDefaultContext(),
            static::createStub(IOInterface::class)
        );

        static::assertSame([[['id' => 'foo']]], $pluginRepo->deletes);
    }

    public function testRefreshPluginsReportsPluginsWithoutAutoloadInformation(): void
    {
        $package = $this->getComposerPackage();
        $package->setAutoload([]);

        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginFinder->method('findPlugins')->willReturn([
            $this->getPluginFromFileSystemStruct($package),
        ]);
        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);

        $errors = $this->getPluginService($pluginRepo, $pluginFinder)->refreshPlugins(
            Context::createDefaultContext(),
            static::createStub(IOInterface::class)
        );

        static::assertCount(1, $errors);
        static::assertInstanceOf(PluginComposerJsonInvalidException::class, $errors->first());
        static::assertSame([], $pluginRepo->upserts);
    }

    public function testRefreshPluginsKeepsCurrentVersionAndSetsUpgradeVersionForAnInstalledPlugin(): void
    {
        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginFinder->method('findPlugins')->willReturn([$this->getPluginFromFileSystemStruct($this->getComposerPackage(version: '2.0.0'))]);

        $plugin = (new PluginEntity())->assign([
            'id' => 'foo',
            'baseClass' => 'foo',
            'version' => '1.0.0',
            'installedAt' => new \DateTimeImmutable(),
        ]);
        $pluginRepo = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $this->getPluginService($pluginRepo, $pluginFinder)->refreshPlugins(
            Context::createDefaultContext(),
            static::createStub(IOInterface::class)
        );

        static::assertSame('1.0.0', $pluginRepo->upserts[0][0]['version']);
        static::assertSame('2.0.0', $pluginRepo->upserts[0][0]['upgradeVersion']);
    }

    private function getComposerPackage(string $version = '1.0.0'): CompletePackage
    {
        $completePackage = new CompletePackage('foo', $version, $version);
        $completePackage->setAutoload([
            'psr-4' => [
                'Foo\\' => 'bar',
            ],
        ]);
        $completePackage->setExtra([
            'label' => [
                'en-GB' => 'foo',
            ],
            'description' => [
                'en-GB' => 'foo',
            ],
        ]);

        return $completePackage;
    }

    private function getPluginFromFileSystemStruct(CompletePackage $package): PluginFromFileSystemStruct
    {
        $plugin = new PluginFromFileSystemStruct();
        $plugin->assign([
            'baseClass' => 'foo',
            'path' => __DIR__,
            'composerPackage' => $package,
            'managedByComposer' => true,
        ]);

        return $plugin;
    }

    /**
     * @param StaticEntityRepository<PluginCollection> $pluginRepo
     */
    private function getPluginService(StaticEntityRepository $pluginRepo, PluginFinder $pluginFinder): PluginService
    {
        return new PluginService(
            __DIR__,
            __DIR__,
            $pluginRepo,
            $this->getLanguageRepository(),
            $pluginFinder,
            new VersionSanitizer()
        );
    }

    /**
     * @return StaticEntityRepository<LanguageCollection>
     */
    private function getLanguageRepository(): StaticEntityRepository
    {
        $language = new LanguageEntity();
        $language->setId('foo');

        $repo = new StaticEntityRepository([new LanguageCollection([$language]), new LanguageCollection([$language])]);

        return $repo;
    }
}
