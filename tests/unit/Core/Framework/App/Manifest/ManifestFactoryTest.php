<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ManifestFactory::class)]
class ManifestFactoryTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $factory = new ManifestFactory(new StaticSourceResolver([]));

        $manifest = $factory->createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertSame(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }

    public function testCreateFromApp(): void
    {
        $factory = new ManifestFactory(new StaticSourceResolver([
            'test' => new Filesystem(__DIR__ . '/_fixtures/test'),
        ]));

        $app = new AppEntity();
        $app->setId('test-app');
        $app->setName('test');

        $manifest = $factory->createFromApp($app);

        static::assertSame(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }
}
