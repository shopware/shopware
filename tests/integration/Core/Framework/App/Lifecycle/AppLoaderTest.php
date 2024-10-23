<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class AppLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testLoad(): void
    {
        $appLoader = $this->getAppLoader(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(8, $manifests);
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

        static::assertCount(8, $manifests);
        foreach ($manifests as $manifest) {
            static::assertInstanceOf(Manifest::class, $manifest);
        }
    }
}
