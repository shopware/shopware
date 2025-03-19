<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\Version\VersionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\PluginCollection;
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
#[CoversClass(PluginService::class)]
class PluginServiceTest extends TestCase
{
    public function testPluginsAddsPlugin(): void
    {
        $pluginFinder = $this->createMock(PluginFinder::class);
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

        /** @var StaticEntityRepository<PluginCollection> $pluginRepo */
        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        $pluginService->refreshPlugins(Context::createDefaultContext(), $this->createMock(IOInterface::class));

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
        $pluginFinder = $this->createMock(PluginFinder::class);
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

        /** @var StaticEntityRepository<PluginCollection> $pluginRepo */
        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        $pluginService->refreshPlugins(Context::createDefaultContext(), $this->createMock(IOInterface::class));

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
        /** @var StaticEntityRepository<PluginCollection> $pluginRepo */
        $pluginRepo = new StaticEntityRepository([new PluginCollection()]);
        $pluginFinder = $this->createMock(PluginFinder::class);
        $pluginService = $this->getPluginService($pluginRepo, $pluginFinder);

        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage('Plugin by name "foo" not found.');
        $pluginService->getPluginByName('foo', Context::createDefaultContext());
    }

    private function getComposerPackage(): CompletePackage
    {
        $completePackage = new CompletePackage('foo', '1.0.0', '1.0.0');
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

        /** @var StaticEntityRepository<LanguageCollection> $repo */
        $repo = new StaticEntityRepository([new LanguageCollection([$language]), new LanguageCollection([$language])]);

        return $repo;
    }
}
