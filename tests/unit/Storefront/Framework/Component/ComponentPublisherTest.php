<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Component;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Component\ComponentPublisher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

/**
 * @internal
 */
#[CoversClass(ComponentPublisher::class)]
class ComponentPublisherTest extends TestCase
{
    private const PROJECT_DIR = '/app';

    private Filesystem $publicFilesystem;

    private Filesystem $tempFilesystem;

    private LocalFilesystem&MockObject $localFilesystem;

    /**
     * In-memory map of absolute path → content that backs the mocked
     * {@see LocalFilesystem}. All `exists()` / `readFile()` calls made by
     * `ComponentPublisher` are answered from this map, so the test never
     * touches the real filesystem.
     *
     * @var array<string, string>
     */
    private array $localFiles = [];

    protected function setUp(): void
    {
        $this->publicFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->localFilesystem = $this->createMock(LocalFilesystem::class);

        $this->localFilesystem
            ->method('exists')
            ->willReturnCallback(
                fn ($path): bool => \is_string($path) && isset($this->localFiles[self::normalizePath($path)]),
            );

        $this->localFilesystem
            ->method('readFile')
            ->willReturnCallback(function (string $path): string {
                $normalized = self::normalizePath($path);
                if (!isset($this->localFiles[$normalized])) {
                    throw new IOException(\sprintf('File "%s" not found in virtual filesystem.', $path));
                }

                return $this->localFiles[$normalized];
            });
    }

    public function testPublishBundleCopiesFilesAndWritesManifest(): void
    {
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button/Primary.js' => [
                    'file' => 'MyExtension/Button/Primary.js',
                    'name' => 'MyExtension/Button/Primary',
                    'isEntry' => true,
                ],
            ],
            'scss' => [
                'MyExtension/Button/Primary.scss' => [
                    'file' => 'MyExtension/Button/Primary-abc123.css',
                    'name' => 'MyExtension/Button/Primary.scss',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary.js'));
        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary-abc123.css'));

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('MyExtension:Button:Primary', $manifest);
        static::assertSame(
            [
                'js' => '/components/MyExtension/Button/Primary.js',
                'css' => ['/components/MyExtension/Button/Primary-abc123.css'],
            ],
            $manifest['MyExtension:Button:Primary'],
        );
    }

    public function testPublishBundleSupportsPluginRootPathWithSrcResources(): void
    {
        $bundleRoot = self::PROJECT_DIR . '/custom/plugins/MyExtension';
        $distDir = $bundleRoot . '/src/Resources/app/storefront/dist-es/components';

        $this->writeLocalFile(
            $distDir . '/.vite/manifest.json',
            (string) json_encode([
                'MyExtension/Button/Primary.js' => [
                    'file' => 'MyExtension/Button/Primary.js',
                    'name' => 'MyExtension/Button/Primary',
                    'isEntry' => true,
                ],
                'MyExtension/Button/Primary.scss' => [
                    'file' => 'MyExtension/Button/Primary-abc123.css',
                    'name' => 'MyExtension/Button/Primary.scss',
                    'isEntry' => true,
                ],
            ], \JSON_THROW_ON_ERROR),
        );
        $this->writeLocalFile($distDir . '/MyExtension/Button/Primary.js', 'dummy js');
        $this->writeLocalFile($distDir . '/MyExtension/Button/Primary-abc123.css', 'dummy css');

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleRoot, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary.js'));
        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary-abc123.css'));

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('MyExtension:Button:Primary', $manifest);
    }

    public function testPublishBundleSupportsProjectRelativeBundlePath(): void
    {
        $bundleRoot = self::PROJECT_DIR . '/custom/plugins/MyExtension';
        $distDir = $bundleRoot . '/src/Resources/app/storefront/dist-es/components';

        $this->writeLocalFile(
            $distDir . '/.vite/manifest.json',
            (string) json_encode([
                'MyExtension/Button/Primary.js' => [
                    'file' => 'MyExtension/Button/Primary.js',
                    'name' => 'MyExtension/Button/Primary',
                    'isEntry' => true,
                ],
            ], \JSON_THROW_ON_ERROR),
        );
        $this->writeLocalFile($distDir . '/MyExtension/Button/Primary.js', 'dummy js');

        $publisher = $this->createPublisher();
        $publisher->publishBundle('custom/plugins/MyExtension/', 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary.js'));
    }

    /**
     * Plain-CSS components flow through Vite's virtual-CSS-module shim, which
     * writes a manifest entry whose `name` field keeps the `.css` suffix (for
     * build-time collision avoidance against same-named JS entries). The
     * publisher must strip that suffix when deriving the runtime tag so a
     * plain `Card.css` component is indistinguishable from a `Card.scss` one
     * at runtime.
     */
    public function testPublishBundleStripsCssSuffixFromPlainCssEntryName(): void
    {
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button/Primary.js' => [
                    'file' => 'MyExtension/Button/Primary.js',
                    'name' => 'MyExtension/Button/Primary',
                    'isEntry' => true,
                ],
            ],
            'css' => [
                'MyExtension/Button/Primary.css' => [
                    'file' => 'MyExtension/Button/Primary-def456.css',
                    'name' => 'MyExtension/Button/Primary.css',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary.js'));
        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button/Primary-def456.css'));

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('MyExtension:Button:Primary', $manifest);
        static::assertSame(
            [
                'js' => '/components/MyExtension/Button/Primary.js',
                'css' => ['/components/MyExtension/Button/Primary-def456.css'],
            ],
            $manifest['MyExtension:Button:Primary'],
        );
    }

    public function testPublishBundleSupportsCssOnlyComponentWithoutJsEntry(): void
    {
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'css' => [
                'MyExtension/PrintStyles.css' => [
                    'file' => 'MyExtension/PrintStyles-abc.css',
                    'name' => 'MyExtension/PrintStyles.css',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('MyExtension:PrintStyles', $manifest);
        static::assertSame(
            ['css' => ['/components/MyExtension/PrintStyles-abc.css']],
            $manifest['MyExtension:PrintStyles'],
        );
    }

    public function testPublishBundleCopiesOriginalFileContents(): void
    {
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button.js' => [
                    'file' => 'MyExtension/Button.js',
                    'name' => 'MyExtension/Button',
                    'isEntry' => true,
                ],
            ],
        ]);

        // Overwrite the auto-generated dummy body with a recognizable payload.
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/MyExtension/Button.js',
            'console.log("hello from button");',
        );

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertSame(
            'console.log("hello from button");',
            $this->publicFilesystem->read('components/MyExtension/Button.js'),
        );
    }

    public function testPublishBundleIsNoopWhenNoViteManifestExists(): void
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/BundleWithoutBuild';

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'BundleWithoutBuild');

        static::assertFalse($this->publicFilesystem->directoryExists('components/BundleWithoutBuild'));
        static::assertFalse($this->tempFilesystem->fileExists(ComponentPublisher::MANIFEST_PATH));
    }

    public function testPublishBundleIsNoopWhenViteManifestContainsInvalidJson(): void
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/BrokenBundle';
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/.vite/manifest.json',
            'not json {{',
        );

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'BrokenBundle');

        static::assertFalse($this->publicFilesystem->directoryExists('components/BrokenBundle'));
        static::assertSame([], $publisher->readComponentManifest());
    }

    public function testPublishBundleOnlyCopiesFilesListedInTheManifest(): void
    {
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button.js' => [
                    'file' => 'MyExtension/Button.js',
                    'name' => 'MyExtension/Button',
                    'isEntry' => true,
                ],
            ],
        ]);

        // Stale leftover from a previous build — also written to .vite/ to make sure
        // Vite internals are never copied either.
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/.vite/vendor-map.json',
            '{"debounce":"MyExtension/vendor/debounce-abc.js"}',
        );
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/MyExtension/stale.js',
            'stale',
        );

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button.js'));
        static::assertFalse($this->publicFilesystem->fileExists('components/.vite/manifest.json'));
        static::assertFalse($this->publicFilesystem->fileExists('components/.vite/vendor-map.json'));
        static::assertFalse($this->publicFilesystem->fileExists('components/MyExtension/stale.js'));
    }

    public function testPublishBundleCopiesCssFilesListedUnderCssArray(): void
    {
        // When Vite inlines an SCSS import from a JS entry it lists the emitted
        // CSS under the entry's `css[]` rather than as a standalone entry. Those
        // CSS files still need to be copied to public/components/ and recorded
        // in the manifest entry for the component.
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button.js' => [
                    'file' => 'MyExtension/Button-hash.js',
                    'name' => 'MyExtension/Button',
                    'isEntry' => true,
                    'css' => ['MyExtension/Button-hash.css'],
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button-hash.js'));
        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button-hash.css'));

        $manifest = $publisher->readComponentManifest();
        static::assertSame(
            [
                'js' => '/components/MyExtension/Button-hash.js',
                'css' => ['/components/MyExtension/Button-hash.css'],
            ],
            $manifest['MyExtension:Button'],
        );
    }

    public function testPublishBundleRecordsAllCssFilesFromJsEntryArray(): void
    {
        // A JS entry whose module graph pulls in multiple stylesheets (e.g. an
        // in-source SCSS import plus a third-party vendor's `import 'pkg/x.css'`)
        // results in several files in the entry's `css[]`. All of them must be
        // recorded in the manifest in source order so the runtime can load them.
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Slider.js' => [
                    'file' => 'MyExtension/Slider-hash.js',
                    'name' => 'MyExtension/Slider',
                    'isEntry' => true,
                    'css' => [
                        'MyExtension/Slider-own-hash.css',
                        'MyExtension/Slider-vendor-hash.css',
                    ],
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Slider-own-hash.css'));
        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Slider-vendor-hash.css'));

        $manifest = $publisher->readComponentManifest();
        static::assertSame(
            [
                'js' => '/components/MyExtension/Slider-hash.js',
                'css' => [
                    '/components/MyExtension/Slider-own-hash.css',
                    '/components/MyExtension/Slider-vendor-hash.css',
                ],
            ],
            $manifest['MyExtension:Slider'],
        );
    }

    public function testPublishBundleMergesSiblingScssWithJsEntryCssIntoSingleManifestEntry(): void
    {
        // A component that has both a sibling .scss file (canonical pattern,
        // emitted as its own entry whose `file` ends in `.css`) and a JS entry
        // whose module graph attaches additional stylesheets must surface both
        // sources under one manifest entry, in source order, deduped.
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Card.js' => [
                    'file' => 'MyExtension/Card-jshash.js',
                    'name' => 'MyExtension/Card',
                    'isEntry' => true,
                    'css' => ['MyExtension/Card-vendor.css'],
                ],
            ],
            'scss' => [
                'MyExtension/Card.scss' => [
                    'file' => 'MyExtension/Card-scsshash.css',
                    'name' => 'MyExtension/Card.scss',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        $manifest = $publisher->readComponentManifest();
        static::assertSame(
            [
                'js' => '/components/MyExtension/Card-jshash.js',
                // JS-attached CSS comes first because the JS entry is iterated
                // before the SCSS entry in this fixture; sibling SCSS appended after.
                'css' => [
                    '/components/MyExtension/Card-vendor.css',
                    '/components/MyExtension/Card-scsshash.css',
                ],
            ],
            $manifest['MyExtension:Card'],
        );
    }

    public function testPublishBundleDeduplicatesCssEntriesReferencedFromMultipleSources(): void
    {
        // If — for whatever reason — the same CSS file ends up referenced
        // both as a standalone SCSS entry and via a JS entry's `css[]`, the
        // manifest must list it exactly once.
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Card.js' => [
                    'file' => 'MyExtension/Card-jshash.js',
                    'name' => 'MyExtension/Card',
                    'isEntry' => true,
                    'css' => ['MyExtension/Card-shared.css'],
                ],
            ],
            'scss' => [
                'MyExtension/Card.scss' => [
                    'file' => 'MyExtension/Card-shared.css',
                    'name' => 'MyExtension/Card.scss',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        $manifest = $publisher->readComponentManifest();
        static::assertSame(
            [
                'js' => '/components/MyExtension/Card-jshash.js',
                'css' => ['/components/MyExtension/Card-shared.css'],
            ],
            $manifest['MyExtension:Card'],
        );
    }

    public function testPublishBundleCopiesInternalChunkFilesButDoesNotTreatThemAsComponents(): void
    {
        // Vite manifests also contain internal chunks (isEntry=false) — these must
        // still be copied (runtime imports need them) but never become component tags.
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button.js' => [
                    'file' => 'MyExtension/Button.js',
                    'name' => 'MyExtension/Button',
                    'isEntry' => true,
                ],
                'chunks/shared.js' => [
                    'file' => 'MyExtension/chunks/shared-abc.js',
                    'name' => 'shared',
                    'isEntry' => false,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/chunks/shared-abc.js'));

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('MyExtension:Button', $manifest);
        static::assertArrayNotHasKey('shared', $manifest);
    }

    public function testPublishBundleMergesIntoExistingManifest(): void
    {
        $bundleA = $this->createBundleWithViteBuild('ExtensionA', [
            'js' => [
                'ExtensionA/A.js' => [
                    'file' => 'ExtensionA/A.js',
                    'name' => 'ExtensionA/A',
                    'isEntry' => true,
                ],
            ],
        ]);
        $bundleB = $this->createBundleWithViteBuild('ExtensionB', [
            'js' => [
                'ExtensionB/B.js' => [
                    'file' => 'ExtensionB/B.js',
                    'name' => 'ExtensionB/B',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleA, 'ExtensionA');
        $publisher->publishBundle($bundleB, 'ExtensionB');

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('ExtensionA:A', $manifest);
        static::assertArrayHasKey('ExtensionB:B', $manifest);
    }

    public function testUnpublishRemovesDirectoryAndManifestEntries(): void
    {
        $bundleDir = $this->createBundleWithViteBuild('MyExtension', [
            'js' => [
                'MyExtension/Button.js' => [
                    'file' => 'MyExtension/Button.js',
                    'name' => 'MyExtension/Button',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleDir, 'MyExtension');

        static::assertTrue($this->publicFilesystem->fileExists('components/MyExtension/Button.js'));
        static::assertArrayHasKey('MyExtension:Button', $publisher->readComponentManifest());

        $changed = $publisher->unpublish('MyExtension');

        static::assertFalse($this->publicFilesystem->directoryExists('components/MyExtension'));
        static::assertArrayNotHasKey('MyExtension:Button', $publisher->readComponentManifest());
        static::assertTrue($changed);
    }

    public function testUnpublishOnlyRemovesEntriesOfTargetedBundle(): void
    {
        $bundleA = $this->createBundleWithViteBuild('ExtensionA', [
            'js' => [
                'ExtensionA/A.js' => [
                    'file' => 'ExtensionA/A.js',
                    'name' => 'ExtensionA/A',
                    'isEntry' => true,
                ],
            ],
        ]);
        $bundleB = $this->createBundleWithViteBuild('ExtensionB', [
            'js' => [
                'ExtensionB/B.js' => [
                    'file' => 'ExtensionB/B.js',
                    'name' => 'ExtensionB/B',
                    'isEntry' => true,
                ],
            ],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishBundle($bundleA, 'ExtensionA');
        $publisher->publishBundle($bundleB, 'ExtensionB');

        $publisher->unpublish('ExtensionA');

        $manifest = $publisher->readComponentManifest();
        static::assertArrayNotHasKey('ExtensionA:A', $manifest);
        static::assertArrayHasKey('ExtensionB:B', $manifest);

        static::assertFalse($this->publicFilesystem->directoryExists('components/ExtensionA'));
        static::assertTrue($this->publicFilesystem->directoryExists('components/ExtensionB'));
    }

    public function testUnpublishIsSafeWhenNothingWasPublished(): void
    {
        $publisher = $this->createPublisher();

        // Must not throw even if nothing is published yet.
        $changed = $publisher->unpublish('Ghost');

        static::assertSame([], $publisher->readComponentManifest());
        static::assertFalse($changed);
    }

    public function testPublishAllReadsPluginsJsonAndPublishesAllBundles(): void
    {
        $bundleA = $this->createBundleWithViteBuild('ExtensionA', [
            'js' => [
                'ExtensionA/A.js' => [
                    'file' => 'ExtensionA/A.js',
                    'name' => 'ExtensionA/A',
                    'isEntry' => true,
                ],
            ],
        ]);
        $bundleB = $this->createBundleWithViteBuild('ExtensionB', [
            'js' => [
                'ExtensionB/B.js' => [
                    'file' => 'ExtensionB/B.js',
                    'name' => 'ExtensionB/B',
                    'isEntry' => true,
                ],
            ],
        ]);

        $this->writePluginsJson([
            'ExtensionA' => ['basePath' => $this->relative($bundleA)],
            'ExtensionB' => ['basePath' => $this->relative($bundleB)],
        ]);

        $publisher = $this->createPublisher();
        $publisher->publishAll();

        $manifest = $publisher->readComponentManifest();
        static::assertArrayHasKey('ExtensionA:A', $manifest);
        static::assertArrayHasKey('ExtensionB:B', $manifest);
    }

    public function testPublishAllRemovesPreviouslyPublishedStaleFilesBeforeCopying(): void
    {
        $bundleA = $this->createBundleWithViteBuild('ExtensionA', [
            'js' => [
                'ExtensionA/A.js' => [
                    'file' => 'ExtensionA/A.js',
                    'name' => 'ExtensionA/A',
                    'isEntry' => true,
                ],
            ],
        ]);

        $this->writePluginsJson([
            'ExtensionA' => ['basePath' => $this->relative($bundleA)],
        ]);

        $this->publicFilesystem->write('components/stale/old.js', 'stale content');
        static::assertTrue($this->publicFilesystem->fileExists('components/stale/old.js'));

        $publisher = $this->createPublisher();
        $publisher->publishAll();

        static::assertFalse($this->publicFilesystem->fileExists('components/stale/old.js'));
        static::assertTrue($this->publicFilesystem->fileExists('components/ExtensionA/A.js'));
    }

    public function testPublishAllIsNoopWhenPluginsJsonMissing(): void
    {
        $publisher = $this->createPublisher();
        $publisher->publishAll();

        static::assertFalse($this->tempFilesystem->fileExists(ComponentPublisher::MANIFEST_PATH));
    }

    public function testReadComponentManifestReturnsEmptyArrayWhenMissing(): void
    {
        static::assertSame([], $this->createPublisher()->readComponentManifest());
    }

    public function testReadComponentManifestReturnsEmptyArrayOnInvalidJson(): void
    {
        $this->tempFilesystem->write(ComponentPublisher::MANIFEST_PATH, 'not json {{{');

        static::assertSame([], $this->createPublisher()->readComponentManifest());
    }

    public function testBuildExtensionVendorScopesReturnsEmptyArrayWhenPluginsJsonMissing(): void
    {
        static::assertSame([], $this->createPublisher()->buildExtensionVendorScopes('https://shop.test'));
    }

    public function testBuildExtensionVendorScopesSkipsStorefrontBundle(): void
    {
        // The Storefront core bundle's vendor map is handled directly by the
        // ThemeCompiler; the publisher must not emit a scope entry for it.
        $storefrontDir = self::PROJECT_DIR . '/vendor/shopware/storefront';
        $this->writeLocalFile(
            $storefrontDir . '/Resources/app/storefront/dist-es/components/.vite/vendor-map.json',
            '{"debounce":"Storefront/vendor/debounce-abc.js"}',
        );

        $this->writePluginsJson([
            'Storefront' => ['basePath' => $this->relative($storefrontDir)],
        ]);

        static::assertSame(
            [],
            $this->createPublisher()->buildExtensionVendorScopes('https://shop.test'),
        );
    }

    public function testBuildExtensionVendorScopesEmitsScopedImportsForExtensionBundles(): void
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/MyExtension';
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/.vite/vendor-map.json',
            (string) json_encode(['debounce' => 'MyExtension/vendor/debounce-abc.js'], \JSON_THROW_ON_ERROR),
        );

        $this->writePluginsJson([
            'MyExtension' => ['basePath' => $this->relative($bundleDir)],
        ]);

        $scopes = $this->createPublisher()->buildExtensionVendorScopes('https://shop.test');

        static::assertSame(
            [
                'https://shop.test/components/MyExtension/' => [
                    'debounce' => 'https://shop.test/components/MyExtension/vendor/debounce-abc.js',
                ],
            ],
            $scopes,
        );
    }

    public function testBuildExtensionVendorScopesSkipsBundlesWithoutVendorMap(): void
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/MyExtension';
        // No vendor-map.json file written.

        $this->writePluginsJson([
            'MyExtension' => ['basePath' => $this->relative($bundleDir)],
        ]);

        static::assertSame([], $this->createPublisher()->buildExtensionVendorScopes('https://shop.test'));
    }

    public function testBuildExtensionVendorScopesSkipsBundlesWithInvalidVendorMapJson(): void
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/MyExtension';
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/.vite/vendor-map.json',
            'not json',
        );

        $this->writePluginsJson([
            'MyExtension' => ['basePath' => $this->relative($bundleDir)],
        ]);

        static::assertSame([], $this->createPublisher()->buildExtensionVendorScopes('https://shop.test'));
    }

    public function testBuildExtensionVendorScopesTrimsTrailingSlashFromBaseUrl(): void
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/MyExtension';
        $this->writeLocalFile(
            $bundleDir . '/Resources/app/storefront/dist-es/components/.vite/vendor-map.json',
            (string) json_encode(['debounce' => 'MyExtension/vendor/debounce-abc.js'], \JSON_THROW_ON_ERROR),
        );

        $this->writePluginsJson([
            'MyExtension' => ['basePath' => $this->relative($bundleDir)],
        ]);

        $scopes = $this->createPublisher()->buildExtensionVendorScopes('https://shop.test/');

        static::assertArrayHasKey('https://shop.test/components/MyExtension/', $scopes);
    }

    private function createPublisher(): ComponentPublisher
    {
        return new ComponentPublisher(
            $this->publicFilesystem,
            $this->tempFilesystem,
            self::PROJECT_DIR,
            'public',
            $this->localFilesystem,
        );
    }

    /**
     * Registers a file with the virtual local filesystem backing the mocked
     * {@see LocalFilesystem}.
     */
    private function writeLocalFile(string $path, string $content): void
    {
        $this->localFiles[self::normalizePath($path)] = $content;
    }

    /**
     * Collapses repeated forward slashes, mirroring how real filesystems treat
     * paths like `/app/vendor/Foo//Resources/...` as equivalent to the single-slash
     * form.
     */
    private static function normalizePath(string $path): string
    {
        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    /**
     * Registers a fake bundle structure with a Vite build output on the virtual
     * local filesystem. Returns the bundle's absolute root path.
     *
     * The `scss` bucket models Vite entries for sibling `.scss` components (whose
     * manifest `name` keeps the `.scss` suffix). The `css` bucket models entries
     * for sibling plain `.css` components (whose `name` keeps the `.css` suffix
     * and which arrive via the virtual-CSS-module shim pipeline).
     *
     * @param array{
     *     js?: array<string, array<string, mixed>>,
     *     scss?: array<string, array<string, mixed>>,
     *     css?: array<string, array<string, mixed>>
     * } $manifestEntries
     */
    private function createBundleWithViteBuild(string $bundleName, array $manifestEntries): string
    {
        $bundleDir = self::PROJECT_DIR . '/vendor/' . $bundleName;
        $distDir = $bundleDir . '/Resources/app/storefront/dist-es/components';

        $viteManifest = array_merge(
            $manifestEntries['js'] ?? [],
            $manifestEntries['scss'] ?? [],
            $manifestEntries['css'] ?? [],
        );

        $this->writeLocalFile(
            $distDir . '/.vite/manifest.json',
            (string) json_encode($viteManifest, \JSON_THROW_ON_ERROR),
        );

        // Create a dummy body for every output file referenced by the manifest so
        // the publisher has something to read and copy.
        foreach ($viteManifest as $entry) {
            if (isset($entry['file']) && \is_string($entry['file'])) {
                $this->writeLocalFile($distDir . '/' . $entry['file'], 'dummy content');
            }

            if (!isset($entry['css']) || !\is_array($entry['css'])) {
                continue;
            }
            foreach ($entry['css'] as $cssFile) {
                if (\is_string($cssFile)) {
                    $this->writeLocalFile($distDir . '/' . $cssFile, 'dummy css');
                }
            }
        }

        return $bundleDir;
    }

    /**
     * @param array<string, array{basePath: string}> $plugins
     */
    private function writePluginsJson(array $plugins): void
    {
        $this->writeLocalFile(
            self::PROJECT_DIR . '/var/plugins.json',
            (string) json_encode($plugins, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Returns a project-relative bundle path with the trailing slash that
     * matches the format Shopware writes to var/plugins.json.
     */
    private function relative(string $absPath): string
    {
        return substr($absPath, \strlen(self::PROJECT_DIR . '/')) . '/';
    }
}
