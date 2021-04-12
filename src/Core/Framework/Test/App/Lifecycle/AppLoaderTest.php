<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

class AppLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLoad(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(10, $manifests);
        static::assertInstanceOf(Manifest::class, $manifests['minimal']);
    }

    public function testLoadIgnoresInvalid(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures/invalid');

        $manifests = $appLoader->load();

        static::assertCount(0, $manifests);
    }

    public function testLoadCombinesFolders(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(10, $manifests);
        foreach ($manifests as $manifest) {
            static::assertInstanceOf(Manifest::class, $manifest);
        }
    }

    public function testGetIcon(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        $manifest = $manifests['test'];

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/icon.png',
            $appLoader->getIcon($manifest)
        );
    }

    public function testGetIconReturnsNullOnInvalidIconPath(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        $manifest = $manifests['test'];

        $manifest->getMetadata()->assign(['icon' => 'file/that/dont/exist.png']);

        static::assertNull($appLoader->getIcon($manifest));
    }

    public function testGetConfigurationReturnsNullIfNoConfigIsProvided(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures/test');

        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/test');
        $app = (new AppEntity())->assign(['path' => $path]);

        static::assertNull($appLoader->getConfiguration($app));
    }

    public function testGetConfigurationReturnsParsedConfig(): void
    {
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures/test');

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
        $appLoader = $this->getAppLoaderForFolder(__DIR__ . '/../Manifest/_fixtures/test');

        $path = str_replace($this->getContainer()->getParameter('kernel.project_dir') . '/', '', __DIR__ . '/../Manifest/_fixtures/test');
        $app = (new AppEntity())->assign(['path' => $path]);

        if (!Feature::isActive('FEATURE_NEXT_14408')) {
            static::assertNull($appLoader->getCmsExtensions($app));

            return;
        }

        static::assertNotNull($appLoader->getCmsExtensions($app)->getBlocks());
        static::assertCount(2, $appLoader->getCmsExtensions($app)->getBlocks()->getBlocks());
    }

    private function getAppLoaderForFolder(string $folder): AppLoader
    {
        return new AppLoader(
            $folder,
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(ConfigReader::class)
        );
    }
}
