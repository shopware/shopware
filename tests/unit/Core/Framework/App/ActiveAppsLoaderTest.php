<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal
 */
#[CoversClass(ActiveAppsLoader::class)]
class ActiveAppsLoaderTest extends TestCase
{
    public function testLoadAppsFromDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'name' => 'test',
                    'path' => 'test',
                    'author' => 'test',
                ],
            ]);

        $activeAppsLoader = new ActiveAppsLoader(
            $connection,
            $this->createMock(AbstractAppLoader::class),
            '/'
        );

        $expected = [
            [
                'name' => 'test',
                'path' => 'test',
                'author' => 'test',
            ],
        ];

        // call twice to test it gets cached
        static::assertEquals($expected, $activeAppsLoader->getActiveApps());
        static::assertEquals($expected, $activeAppsLoader->getActiveApps());

        // reset cache

        $activeAppsLoader->reset();

        static::assertEquals($expected, $activeAppsLoader->getActiveApps());
    }

    public function testLoadAppsFromLocal(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willThrowException(new \Exception('test'));

        $appLoader = $this->createMock(AbstractAppLoader::class);

        $xmlFile = __DIR__ . '/_fixtures/manifest.xml';

        $appLoader
            ->method('load')
            ->willReturn([
                Manifest::createFromXmlFile($xmlFile),
            ]);

        $activeAppsLoader = new ActiveAppsLoader(
            $connection,
            $appLoader,
            \dirname($xmlFile, 2)
        );

        $expected = [
            [
                'name' => 'test',
                'path' => \basename(\dirname($xmlFile)),
                'author' => 'shopware AG',
            ],
        ];

        static::assertEquals($expected, $activeAppsLoader->getActiveApps());
    }

    #[BackupGlobals(true)]
    public function testDisabled(): void
    {
        $_SERVER['DISABLE_EXTENSIONS'] = '1';

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::never())
            ->method('fetchAllAssociative');

        $appLoader = $this->createMock(AbstractAppLoader::class);
        $appLoader
            ->expects(static::never())
            ->method('load');

        $activeAppsLoader = new ActiveAppsLoader(
            $connection,
            $appLoader,
            '/'
        );

        static::assertEquals([], $activeAppsLoader->getActiveApps());
    }
}
