<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ActiveAppsLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    /**
     * @var ActiveAppsLoader
     */
    private $activeAppsLoader;

    public function setUp(): void
    {
        $this->activeAppsLoader = $this->getContainer()->get(ActiveAppsLoader::class);
    }

    public function testGetActiveAppsWithActiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test');

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(1, $activeApps);
        static::assertEquals('SwagApp', $activeApps[0]['name']);
    }

    public function testGetActiveAppsWithInactiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test', false);

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(0, $activeApps);
    }
}
