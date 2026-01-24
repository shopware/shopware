<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class ActiveAppsLoaderTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use AppSystemTestBehaviour;

    private ActiveAppsLoader $activeAppsLoader;

    protected function setUp(): void
    {
        $this->activeAppsLoader = static::getContainer()->get(ActiveAppsLoader::class);
    }

    public function testGetActiveAppsWithActiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test');

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(1, $activeApps);
        static::assertSame('test', $activeApps[0]['name']);
    }

    public function testGetActiveAppsWithInactiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test', false);

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(0, $activeApps);
    }
}
