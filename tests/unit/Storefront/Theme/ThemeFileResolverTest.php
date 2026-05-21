<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithBundleRelativeFiles\ThemeWithBundleRelativeFiles;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithInvalidBundleReference\ThemeWithInvalidBundleReference;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ThemeFileResolver::class)]
class ThemeFileResolverTest extends TestCase
{
    public function testBundleRelativeFileThrowsExceptionForMissingFileInExistingBundle(): void
    {
        $themePluginBundle = new ThemeWithBundleRelativeFiles();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $config->setStyleFiles(
            FileCollection::createFromArray(['@MockStorefront/app/storefront/src/scss/does-not-exist.scss'])
        );
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection([$config, $storefront]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithBundleRelativeFiles' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);
        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithBundleRelativeFiles', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $resolver = new ThemeFileResolver(new ThemeFilesystemResolver($sourceResolver, $kernel));

        $this->expectExceptionObject(
            ThemeException::themeCompileException(
                'ThemeWithBundleRelativeFiles',
                'Unable to resolve file "@MockStorefront/app/storefront/src/scss/does-not-exist.scss". File does not exist.'
            )
        );
        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testNamespaceReferenceThrowsExceptionWhenThemeIsMissing(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@NonExistentTheme']));
        $config->setScriptFiles(new FileCollection());

        $resolver = new ThemeFileResolver($this->createMock(ThemeFilesystemResolver::class));

        $this->expectExceptionObject(ThemeException::couldNotFindThemeByName('NonExistentTheme'));
        $resolver->resolveStyleFiles($config, new StorefrontPluginConfigurationCollection([$config]), false);
    }

    public function testResolveScriptFilesAddsOwnEntryAfterIncludedThemeWhenIncludeComesFirst(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $ownEntry = tempnam(sys_get_temp_dir(), 'theme-file-resolver-own-entry-');
        if ($ownEntry === false) {
            static::fail('Could not create temporary file for own entry.');
        }
        $includedEntry = tempnam(sys_get_temp_dir(), 'theme-file-resolver-included-entry-');
        if ($includedEntry === false) {
            @unlink($ownEntry);
            static::fail('Could not create temporary file for included entry.');
        }

        $config->setStorefrontEntryFilepath($ownEntry);
        $config->setScriptFiles(FileCollection::createFromArray(['@Storefront', '/tmp/should-be-skipped.js']));

        $storefront = new StorefrontPluginConfiguration('Storefront');
        $storefront->setStorefrontEntryFilepath($includedEntry);
        $storefront->setScriptFiles(new FileCollection());

        $filesystem = $this->createMock(\Shopware\Core\Framework\Util\Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn($filesystem);

        $resolver = new ThemeFileResolver($themeFilesystemResolver);
        $configCollection = new StorefrontPluginConfigurationCollection([$config, $storefront]);

        try {
            $result = $resolver->resolveScriptFiles($config, $configCollection, true);

            static::assertSame([$includedEntry, $ownEntry], $result->getFilepaths());
            static::assertSame('storefront', $result->first()?->assetName);
            static::assertSame($config->getAssetName(), $result->last()?->assetName);
        } finally {
            @unlink($ownEntry);
            @unlink($includedEntry);
        }
    }

    public function testResolveStyleFilesReturnsEmptyCollectionForCircularThemeIncludes(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@OtherTheme']));
        $config->setScriptFiles(new FileCollection());

        $otherConfig = new StorefrontPluginConfiguration('OtherTheme');
        $otherConfig->setStyleFiles(FileCollection::createFromArray(['@TestTheme']));
        $otherConfig->setScriptFiles(new FileCollection());

        $resolver = new ThemeFileResolver($this->createMock(ThemeFilesystemResolver::class));
        $result = $resolver->resolveStyleFiles(
            $config,
            new StorefrontPluginConfigurationCollection([$config, $otherConfig]),
            false
        );

        static::assertCount(0, $result);
    }

    public function testConvertPathsToAbsoluteAlsoConvertsResolveMappingEntries(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $file = new File('app/storefront/src/scss/base.scss', ['vendor' => 'app/storefront/vendor']);
        $files = new FileCollection();
        $files->add($file);
        $config->setStyleFiles($files);

        $existingFilePath = tempnam(sys_get_temp_dir(), 'theme-file-resolver-');
        if ($existingFilePath === false) {
            static::fail('Could not create temporary file for test.');
        }

        $filesystem = $this->createMock(\Shopware\Core\Framework\Util\Filesystem::class);
        $filesystem->method('has')->willReturnMap([
            ['Resources', 'app/storefront/src/scss/base.scss', true],
            ['Resources', 'app/storefront/vendor', true],
        ]);
        $filesystem->method('realpath')->willReturnMap([
            ['Resources', 'app/storefront/src/scss/base.scss', $existingFilePath],
            ['Resources', 'app/storefront/vendor', '/tmp/Resources/app/storefront/vendor'],
        ]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn($filesystem);

        $resolver = new ThemeFileResolver($themeFilesystemResolver);
        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        try {
            $resolvedFiles = $resolver->resolveFiles($config, $configCollection, false);

            static::assertSame([$existingFilePath], $resolvedFiles[ThemeFileResolver::STYLE_FILES]->getFilepaths());
            static::assertSame(
                ['vendor' => '/tmp/Resources/app/storefront/vendor'],
                $resolvedFiles[ThemeFileResolver::STYLE_FILES]->getResolveMappings()
            );
        } finally {
            @unlink($existingFilePath);
        }
    }

    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);

        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);

        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontSkinScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, \JSON_PRETTY_PRINT);
        $expected = '/Resources\/app\/storefront\/src\/scss\/skin\/shopware\/_base.scss';

        static::assertStringContainsString($expected, (string) $actual);
    }

    public function testResolvedFilesDoNotIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontBootstrapScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithStorefrontBootstrapScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontBootstrapScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, \JSON_PRETTY_PRINT);
        $notExpected = '/Resources\/app\/storefront\/src\/scss\/skin\/shopware\/_base.scss';

        static::assertStringNotContainsString($notExpected, (string) $actual);
    }

    public function testResolvedFilesDontContainDuplicates(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );
        $scriptFiles = $resolvedFiles['script'];
        $actual = $scriptFiles->getFilepaths();
        $expected = array_unique($scriptFiles->getFilepaths());

        static::assertSame($expected, $actual);
    }

    public function testParentThemeIncludesPlugins(): void
    {
        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeNotIncludingPluginJsAndCss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeNotIncludingPluginJsAndCss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $scriptFiles = $resolvedFiles['script'];
        $pluginScriptFile = 'SimplePlugin/Resources/app/storefront/dist/storefront/js/simple-plugin/simple-plugin.js';
        $pluginScriptIncluded = false;

        foreach ($scriptFiles->getFilepaths() as $path) {
            if (mb_stripos((string) $path, $pluginScriptFile) !== false) {
                $pluginScriptIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginScriptIncluded);

        $styleFiles = $resolvedFiles['style'];
        $pluginEntryStyleFile = 'SimplePlugin/Resources/app/storefront/src/scss/base.scss';
        $pluginStyleIncluded = false;

        foreach ($styleFiles->getFilepaths() as $path) {
            if (mb_stripos((string) $path, $pluginEntryStyleFile) !== false) {
                $pluginStyleIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginStyleIncluded);
    }

    public function testResolveFilesDoesntAffectPassedArguments(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $firstFile = $config->getStyleFiles()->first();
        static::assertNotNull($firstFile);
        $currentPath = $firstFile->getFilepath();

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontSkinScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        // Path is still relative
        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());

        $config->setScriptFiles(new FileCollection());
        $config->setStorefrontEntryFilepath(__FILE__);

        (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            true
        );

        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());
    }

    public function testCircularReferencePreventionReturnsEmptyCollection(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        // This should not cause infinite loop - circular references are handled internally
        $result = $resolver->resolveScriptFiles($config, $configCollection, false);

        static::assertGreaterThan(0, $result->count());
    }

    public function testFileDeduplicationAcrossNamespaces(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $styleFiles = $resolvedFiles['style'];
        $stylePaths = $styleFiles->getFilepaths();

        // Check that all paths are unique
        static::assertCount(\count(array_unique($stylePaths)), $stylePaths, 'Style files should not contain duplicates');

        $scriptFiles = $resolvedFiles['script'];
        $scriptPaths = $scriptFiles->getFilepaths();

        // Check that all paths are unique
        static::assertCount(\count(array_unique($scriptPaths)), $scriptPaths, 'Script files should not contain duplicates');
    }

    public function testBundleRelativeFileResolution(): void
    {
        $themePluginBundle = new ThemeWithBundleRelativeFiles();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithBundleRelativeFiles' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithBundleRelativeFiles', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $styleFiles = $resolvedFiles['style'];
        $paths = $styleFiles->getFilepaths();

        // Check that overrides.scss is resolved
        $overridesFound = false;
        $overridesPosition = -1;
        foreach ($paths as $index => $path) {
            if (str_contains((string) $path, 'overrides.scss')) {
                $overridesFound = true;
                $overridesPosition = $index;
                break;
            }
        }
        static::assertTrue($overridesFound, 'Bundle-relative file @MockStorefront/app/storefront/src/scss/overrides.scss should be resolved');

        // Check that overrides.scss appears only once (not duplicated when @MockStorefront is expanded)
        $overridesCount = 0;
        foreach ($paths as $path) {
            if (str_contains((string) $path, 'overrides.scss')) {
                ++$overridesCount;
            }
        }
        static::assertSame(1, $overridesCount, 'overrides.scss should appear only once (no duplication)');

        // Check that overrides.scss appears before base.scss (order preservation)
        $basePosition = -1;
        foreach ($paths as $index => $path) {
            if (str_contains((string) $path, 'base.scss')) {
                $basePosition = $index;
                break;
            }
        }

        if ($basePosition !== -1) {
            static::assertLessThan($basePosition, $overridesPosition, 'Bundle-relative file should appear in order before full bundle expansion');
        }

        // Check that custom.scss from the theme itself is also included
        $customFound = false;
        foreach ($paths as $path) {
            if (str_contains((string) $path, 'custom.scss')) {
                $customFound = true;
                break;
            }
        }
        static::assertTrue($customFound, 'Direct file reference custom.scss should be resolved');
    }

    public function testBundleRelativeFileThrowsExceptionForMissingBundle(): void
    {
        $themePluginBundle = new ThemeWithInvalidBundleReference();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithInvalidBundleReference' => $themePluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithInvalidBundleReference', $themePluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $this->expectExceptionObject(
            ThemeException::couldNotFindThemeByName('NonExistentBundle')
        );

        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testStorefrontBootstrapNamespaceResolvesToBaseScssWithVendorMapping(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@StorefrontBootstrap']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        $file = $result->first();
        static::assertNotNull($file);
        static::assertStringEndsWith('Resources/app/storefront/src/scss/base.scss', $file->getFilepath());
        static::assertArrayHasKey('vendor', $file->getResolveMapping());
        static::assertStringEndsWith('Resources/app/storefront/vendor', $file->getResolveMapping()['vendor']);
    }

    public function testDirectFileMissingMatchingOldJsStructureThrowsException(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');

        // Path ends with "test-plugin/test-plugin.css" — matches the old-JS-structure pattern → throw
        $file = new File('/nonexistent/test-plugin/test-plugin.css', [], 'test-plugin');
        $fileCollection = new FileCollection();
        $fileCollection->add($file);
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem([]));

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $this->expectException(ThemeCompileException::class);
        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testDirectFileMissingNotMatchingOldJsStructureIsSilentlySkipped(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');

        // assetName is 'test-plugin', but file is 'other-file.css' — no old-JS-structure match → silent skip
        $file = new File('/nonexistent/other-file.css', [], 'test-plugin');
        $fileCollection = new FileCollection();
        $fileCollection->add($file);
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem([]));

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(0, $result, 'Missing file with non-matching path structure should be silently skipped');
    }

    public function testPluginsNamespaceExpandsAllNonThemePlugins(): void
    {
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        // Theme that directly declares @Plugins — no @MockStorefront indirection
        $themeConfig = new StorefrontPluginConfiguration('TestTheme');
        $themeConfig->setIsTheme(true);
        $themeConfig->setStyleFiles(FileCollection::createFromArray(['@Plugins']));
        $themeConfig->setScriptFiles(new FileCollection());

        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection([$themeConfig, $plugin]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'SimplePlugin' => $pluginBundle,
        ]);
        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver($sourceResolver, $kernel);
        $result = (new ThemeFileResolver($themeFilesystemResolver))
            ->resolveStyleFiles($themeConfig, $configCollection, false);

        $paths = $result->getFilepaths();
        $pluginStyleIncluded = false;
        foreach ($paths as $path) {
            if (mb_stripos((string) $path, 'SimplePlugin') !== false) {
                $pluginStyleIncluded = true;
                break;
            }
        }

        static::assertTrue($pluginStyleIncluded, '@Plugins should include style files from non-theme plugins');
    }

    public function testDuplicateNamespaceReferenceIsExpandedOnlyOnce(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        // @StorefrontBootstrap listed twice — base.scss should still appear exactly once
        $config->setStyleFiles(FileCollection::createFromArray(['@StorefrontBootstrap', '@StorefrontBootstrap']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result, 'Duplicate @Namespace reference should be expanded only once');
    }

    public function testNamespaceReferenceWithoutSlashIsResolvedAsNamespace(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $config->setStyleFiles(FileCollection::createFromArray(['@MockStorefront']));
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection([$config, $storefront]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);
        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontSkinScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $resolver = new ThemeFileResolver(new ThemeFilesystemResolver($sourceResolver, $kernel));
        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        $paths = $result->getFilepaths();
        static::assertNotEmpty($paths);
        static::assertTrue(
            (bool) array_filter(
                $paths,
                static fn (?string $path): bool => \is_string($path) && str_contains($path, 'MockStorefront/Resources/app/storefront/src/scss/base.scss')
            )
        );
    }

    public function testNonNamespaceFilePathIsHandledAsDirectFileReference(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $fileCollection = new FileCollection();
        $fileCollection->add(new File(__DIR__ . '/fixtures/MockStorefront/Resources/app/storefront/src/scss/base.scss'));
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $resolver = new ThemeFileResolver($this->createMock(ThemeFilesystemResolver::class));
        $result = $resolver->resolveStyleFiles($config, new StorefrontPluginConfigurationCollection([$config]), false);

        static::assertCount(1, $result);
        static::assertSame(
            __DIR__ . '/fixtures/MockStorefront/Resources/app/storefront/src/scss/base.scss',
            $result->first()?->getFilepath()
        );
    }
}
