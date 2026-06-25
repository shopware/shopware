<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util\AssetValidation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetFilterResult;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetValidator;
use Shopware\Core\Framework\Plugin\Util\AssetValidation\AdministrationExtensionAssetViolation;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(AdministrationExtensionAssetFilterResult::class)]
#[CoversClass(AdministrationExtensionAssetValidator::class)]
#[CoversClass(AdministrationExtensionAssetViolation::class)]
class AdministrationExtensionAssetValidatorTest extends TestCase
{
    private Filesystem $filesystem;

    /**
     * @var list<string>
     */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectories);
    }

    public function testExistingCssAndJsRemainInConfig(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles([
            'assets/app.css' => '',
            'assets/app.js' => '',
        ]);
        $validator = new AdministrationExtensionAssetValidator($this->filesystem);

        $cssUrl = '/bundles/acme/administration/assets/app.css?v=1#main';
        $jsUrl = '/bundles/acme/administration/assets/app.js?v=1#main';

        $cssResult = $validator->filterAssetUrls($bundle, [$cssUrl], 'css');
        $jsResult = $validator->filterAssetUrls($bundle, [$jsUrl], 'js');

        static::assertSame([$cssUrl], $cssResult->assets);
        static::assertSame([], $cssResult->violations);
        static::assertSame([$jsUrl], $jsResult->assets);
        static::assertSame([], $jsResult->violations);
    }

    public function testMissingCssIsRemovedWhileExistingJsRemains(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles([
            'assets/app.js' => '',
        ]);
        $validator = new AdministrationExtensionAssetValidator($this->filesystem);

        $cssResult = $validator->filterAssetUrls($bundle, ['/bundles/acme/administration/assets/missing.css'], 'css');
        $jsResult = $validator->filterAssetUrls($bundle, ['/bundles/acme/administration/assets/app.js'], 'js');

        static::assertSame([], $cssResult->assets);
        static::assertCount(1, $cssResult->violations);
        static::assertSame('css', $cssResult->violations[0]->assetType);
        static::assertTrue($cssResult->violations[0]->isMissingAsset());
        static::assertSame(['/bundles/acme/administration/assets/app.js'], $jsResult->assets);
        static::assertSame([], $jsResult->violations);
    }

    public function testExternalOrUnmappableAssetUrlIsPreserved(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles();
        $validator = new AdministrationExtensionAssetValidator($this->filesystem);
        $externalUrl = 'https://cdn.example.com/extensions/acme/app.css';

        $result = $validator->filterAssetUrls($bundle, [$externalUrl], 'css');

        static::assertSame([$externalUrl], $result->assets);
        static::assertSame([], $result->violations);
    }

    public function testPathTraversalIsRejected(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles([
            'assets/app.js' => '',
        ]);
        $validator = new AdministrationExtensionAssetValidator($this->filesystem);

        $result = $validator->filterAssetUrls($bundle, ['/bundles/acme/administration/../secret.js'], 'js');

        static::assertSame([], $result->assets);
        static::assertCount(1, $result->violations);
        static::assertSame('The referenced Administration extension asset path is invalid.', $result->violations[0]->reason);
    }

    public function testSubdirectoryAndAbsoluteAssetBaseUrlsAreParsedByUrlPath(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles([
            'assets/app.js' => '',
        ]);
        $validator = new AdministrationExtensionAssetValidator($this->filesystem);
        $assetUrl = 'https://shop.example.com/subdirectory/bundles/acme/administration/assets/app.js';

        $result = $validator->filterAssetUrls($bundle, [$assetUrl], 'js');

        static::assertSame([$assetUrl], $result->assets);
        static::assertSame([], $result->violations);
    }

    public function testValidateEntrypointsFileReportsMalformedJson(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles();
        $this->writeEntrypointsFile($bundle, '{invalid');

        $validator = new AdministrationExtensionAssetValidator($this->filesystem);
        $violations = $validator->validateEntrypointsFile($bundle);

        static::assertCount(1, $violations);
        static::assertSame('entrypoints', $violations[0]->assetType);
        static::assertStringContainsString('malformed', $violations[0]->reason);
    }

    public function testValidateEntrypointsFileReportsMissingJs(): void
    {
        $bundle = $this->createBundleWithAdministrationFiles();
        $this->writeEntrypointsFile($bundle, json_encode([
            'entryPoints' => [
                'acme-bundle' => [
                    'css' => [],
                    'dynamic' => [],
                    'js' => [
                        '/bundles/acme/administration/assets/missing.js',
                    ],
                    'preload' => [],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));

        $validator = new AdministrationExtensionAssetValidator($this->filesystem);
        $violations = $validator->validateEntrypointsFile($bundle);

        static::assertCount(1, $violations);
        static::assertSame('js', $violations[0]->assetType);
        static::assertTrue($violations[0]->isMissingAsset());
    }

    /**
     * @param array<string, string> $files
     */
    private function createBundleWithAdministrationFiles(array $files = []): BundleFixture
    {
        $bundlePath = Path::join(sys_get_temp_dir(), uniqid('sw-admin-extension-assets-', true));
        $this->temporaryDirectories[] = $bundlePath;

        foreach ($files as $relativePath => $contents) {
            $this->filesystem->dumpFile(
                Path::join($bundlePath, AdministrationExtensionAssetValidator::ADMINISTRATION_PUBLIC_PATH, $relativePath),
                $contents
            );
        }

        return new BundleFixture('AcmeBundle', $bundlePath);
    }

    private function writeEntrypointsFile(BundleFixture $bundle, string $contents): void
    {
        $this->filesystem->dumpFile(
            Path::join($bundle->getPath(), AdministrationExtensionAssetValidator::ENTRYPOINTS_FILE_PATH),
            $contents
        );
    }
}
