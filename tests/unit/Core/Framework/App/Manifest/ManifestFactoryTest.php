<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;

/**
 * @internal
 */
#[CoversClass(ManifestFactory::class)]
class ManifestFactoryTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $factory = new ManifestFactory();

        $manifest = $factory->createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertEquals(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }
}
