<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Lifecycle\AppLoader
 */
class AppLoaderTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $packages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packages = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // @phpstan-ignore-next-line
        InstalledVersions::reload($this->packages);
    }

    public function testGetSnippets(): void
    {
        $expectedSnippet = [];
        $expectedSnippet['en-GB'] = file_get_contents(__DIR__ . '/../_fixtures/Resources/app/administration/snippet/en-GB.json');

        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader(),
            new CustomEntityXmlSchemaValidator()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $snippets = $appLoader->getSnippets($appEntity);
        static::assertEquals($expectedSnippet, $snippets);
    }

    public function testLoadAppByComposer(): void
    {
        $packages = InstalledVersions::getAllRawData();

        $modified = $packages[0];
        static::assertIsArray($modified);
        $modified['versions'] = [
            // Points to path that does not exists
            'swag/app' => [
                'dev_requirement' => false,
                'type' => AppLoader::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixtures/',
            ],
        ];

        InstalledVersions::reload($modified);

        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader(),
            new CustomEntityXmlSchemaValidator()
        );

        $apps = $appLoader->load();
        static::assertCount(1, $apps);
        static::assertArrayHasKey('test', $apps);

        $app = $apps['test'];

        static::assertTrue($app->isManagedByComposer());

        static::assertEquals('test', $app->getMetadata()->getName());
        static::assertEquals('1.0.0', $app->getMetadata()->getVersion());

        static::expectException(AppException::class);
        $appLoader->deleteApp('test');
    }
}
