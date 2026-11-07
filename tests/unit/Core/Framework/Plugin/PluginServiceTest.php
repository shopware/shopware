<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\IO\IOInterface;
use Composer\Package\CompleteAliasPackage;
use Composer\Package\CompletePackage;
use Composer\Package\Version\VersionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
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

    public function testRefreshPluginsLooksUpEachLocaleOnce(): void
    {
        $germanLanguageId = Uuid::randomHex();
        $lookedUpLocales = new \ArrayObject();
        $pluginRepo = new StaticEntityRepository([new PluginCollection(), new PluginCollection()]);
        $pluginService = $this->getPluginServiceForLocales(
            $pluginRepo,
            ['en-GB', 'de-DE'],
            ['en-GB' => Defaults::LANGUAGE_SYSTEM, 'de-DE' => $germanLanguageId],
            $lookedUpLocales
        );

        $pluginService->refreshPlugins(Context::createDefaultContext(), static::createStub(IOInterface::class));

        static::assertSame(['en-GB', 'de-DE'], $lookedUpLocales->getArrayCopy());
        static::assertCount(2, $pluginRepo->upserts);
        foreach ($pluginRepo->upserts as $upsert) {
            $name = $upsert[0]['name'];

            static::assertSame([
                Defaults::LANGUAGE_SYSTEM => ['label' => $name . ' en-GB', 'description' => $name . ' en-GB'],
                $germanLanguageId => ['label' => $name . ' de-DE', 'description' => $name . ' de-DE'],
            ], $upsert[0]['translations']);
        }

        $pluginService->refreshPlugins(Context::createDefaultContext(), static::createStub(IOInterface::class));

        static::assertSame(['en-GB', 'de-DE', 'en-GB', 'de-DE'], $lookedUpLocales->getArrayCopy(), 'Every refresh has to look the locales up again');
    }

    public function testRefreshPluginsLooksUpALocaleWithoutLanguageOnce(): void
    {
        $lookedUpLocales = new \ArrayObject();
        $pluginRepo = new StaticEntityRepository([new PluginCollection(), new PluginCollection()]);
        $pluginService = $this->getPluginServiceForLocales(
            $pluginRepo,
            ['en-GB', 'nl-NL'],
            ['en-GB' => Defaults::LANGUAGE_SYSTEM],
            $lookedUpLocales
        );

        $pluginService->refreshPlugins(Context::createDefaultContext(), static::createStub(IOInterface::class));

        static::assertSame(['en-GB', 'nl-NL'], $lookedUpLocales->getArrayCopy());
        static::assertCount(2, $pluginRepo->upserts);
        foreach ($pluginRepo->upserts as $upsert) {
            $name = $upsert[0]['name'];

            static::assertSame([
                Defaults::LANGUAGE_SYSTEM => ['label' => $name . ' en-GB', 'description' => $name . ' en-GB'],
            ], $upsert[0]['translations']);
        }

        $pluginService->refreshPlugins(Context::createDefaultContext(), static::createStub(IOInterface::class));

        static::assertSame(['en-GB', 'nl-NL', 'en-GB', 'nl-NL'], $lookedUpLocales->getArrayCopy(), 'Every refresh has to look the locales up again');
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
     * @param StaticEntityRepository<PluginCollection> $pluginRepo
     * @param list<string> $locales
     * @param array<string, string> $languageIdsByLocale
     * @param \ArrayObject<int, string> $lookedUpLocales
     */
    private function getPluginServiceForLocales(StaticEntityRepository $pluginRepo, array $locales, array $languageIdsByLocale, \ArrayObject $lookedUpLocales): PluginService
    {
        $plugins = [];
        foreach (['foo', 'bar'] as $name) {
            $translations = [];
            foreach ($locales as $locale) {
                $translations[$locale] = $name . ' ' . $locale;
            }

            $package = new CompletePackage($name, '1.0.0', '1.0.0');
            $package->setAutoload(['psr-4' => [ucfirst($name) . '\\' => 'src']]);
            $package->setExtra(['label' => $translations, 'description' => $translations]);

            $plugin = new PluginFromFileSystemStruct();
            $plugin->assign(['baseClass' => $name, 'path' => __DIR__, 'composerPackage' => $package, 'managedByComposer' => true]);
            $plugins[] = $plugin;
        }

        $pluginFinder = static::createStub(PluginFinder::class);
        $pluginFinder->method('findPlugins')->willReturn($plugins);

        $languageSearch = static function (Criteria $criteria) use ($languageIdsByLocale, $lookedUpLocales): array {
            $filters = $criteria->getFilters();
            static::assertCount(1, $filters);
            static::assertInstanceOf(EqualsFilter::class, $filters[0]);
            static::assertSame('language.translationCode.code', $filters[0]->getField());

            $locale = $filters[0]->getValue();
            static::assertIsString($locale);
            $lookedUpLocales->append($locale);

            return isset($languageIdsByLocale[$locale]) ? [$languageIdsByLocale[$locale]] : [];
        };

        return new PluginService(
            __DIR__,
            __DIR__,
            $pluginRepo,
            StaticEntityRepository::of(LanguageCollection::class, array_fill(0, 8, $languageSearch)),
            $pluginFinder,
            new VersionSanitizer()
        );
    }

    /**
     * @return StaticEntityRepository<LanguageCollection>
     */
    private function getLanguageRepository(): StaticEntityRepository
    {
        $repo = StaticEntityRepository::of(LanguageCollection::class, [['foo'], ['foo']]);

        return $repo;
    }
}
