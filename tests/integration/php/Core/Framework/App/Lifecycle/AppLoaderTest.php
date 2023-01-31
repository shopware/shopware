<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cms\CmsExtensions as CmsManifest;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;

/**
 * @internal
 */
class AppLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    public function testLoad(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(10, $manifests);
        static::assertInstanceOf(Manifest::class, $manifests['minimal']);
    }

    public function testLoadIgnoresInvalid(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/invalid');

        $manifests = $appLoader->load();

        static::assertCount(0, $manifests);
    }

    public function testLoadCombinesFolders(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(10, $manifests);
        foreach ($manifests as $manifest) {
            static::assertInstanceOf(Manifest::class, $manifest);
        }
    }

    public function testGetIcon(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        $manifest = $manifests['test'];

        $icon = $appLoader->getIcon($manifest);
        static::assertNotNull($icon);

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/icon.png',
            $icon
        );
    }

    public function testGetIconReturnsNullOnInvalidIconPath(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        $manifest = $manifests['test'];

        $manifest->getMetadata()->assign(['icon' => 'file/that/dont/exist.png']);

        static::assertNull($appLoader->getIcon($manifest));
    }

    public function testGetConfigurationReturnsNullIfNoConfigIsProvided(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/test');
        $app = (new AppEntity())->assign(['path' => $path]);

        static::assertNull($appLoader->getConfiguration($app));
    }

    public function testGetConfigurationReturnsParsedConfig(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/withConfig');
        $app = (new AppEntity())->assign(['path' => $path]);

        $expectedConfig = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'de-DE' => 'Grundeinstellungen',
                ],
                'name' => 'TestCard',
                'elements' => [
                    [
                        'type' => 'text',
                        'name' => 'email',
                        'copyable' => true,
                        'label' => [
                            'en-GB' => 'eMail',
                            'de-DE' => 'E-Mail',
                        ],
                        'defaultValue' => 'no-reply@shopware.de',
                    ],
                ],
            ],
        ];
        static::assertEquals($expectedConfig, $appLoader->getConfiguration($app));
    }

    public function testGetCmsExtensions(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/test');
        $app = (new AppEntity())->assign(['path' => $path]);

        $cmsManifest = $appLoader->getCmsExtensions($app);
        static::assertInstanceOf(CmsManifest::class, $cmsManifest);

        $blocks = $cmsManifest->getBlocks();
        static::assertNotNull($blocks);
        static::assertCount(2, $blocks->getBlocks());
    }

    public function testGetAssetPathForAppPath(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures');

        static::assertEquals(
            $this->getContainer()->getParameter('kernel.project_dir') . '/custom/apps/test/Resources/public',
            $appLoader->getAssetPathForAppPath('custom/apps/test')
        );
    }
}
