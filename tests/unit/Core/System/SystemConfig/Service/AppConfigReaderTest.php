<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(AppConfigReader::class)]
class AppConfigReaderTest extends TestCase
{
    public function testReadConfigFromApp(): void
    {
        $app = (new AppEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'TestApp']);

        $fs = new StaticFilesystem([
            'Resources/config/config.xml' => 'config',
        ]);
        $sourceResolver = new StaticSourceResolver(['TestApp' => $fs]);

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->expects(static::once())
            ->method('read')
            ->with('/app-root/Resources/config/config.xml')
            ->willReturn([
                'config1' => 'value',
            ]);

        $appConfigReader = new AppConfigReader($sourceResolver, $configReader);
        static::assertSame(
            [
                'config1' => 'value',
            ],
            $appConfigReader->read($app)
        );
    }

    public function testReadConfigFromAppWhenItHasNone(): void
    {
        $app = (new AppEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'TestApp']);

        $fs = new StaticFilesystem();

        $sourceResolver = new StaticSourceResolver(['TestApp' => $fs]);

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->expects(static::never())->method('read');

        $appConfigReader = new AppConfigReader($sourceResolver, $configReader);
        static::assertNull($appConfigReader->read($app));
    }
}
