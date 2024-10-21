<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Source\NoDatabaseSourceResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(NoDatabaseSourceResolver::class)]
class NoDatabaseSourceResolverTest extends TestCase
{
    public function testExceptionIsThrownIfAppNotInActiveApps(): void
    {
        static::expectExceptionObject(AppException::notFoundByField('TestApp', 'name'));

        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader->expects(static::any())->method('getActiveApps')->willReturn([]);

        $resolver = new NoDatabaseSourceResolver($activeAppsLoader);
        $resolver->filesystem('TestApp');
    }

    public function testFilesystemForActiveAppUsesPath(): void
    {
        $activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoader->expects(static::any())->method('getActiveApps')->willReturn([
            [
                'name' => 'TestApp',
                'path' => '/path/to/app',
            ],
        ]);

        $resolver = new NoDatabaseSourceResolver($activeAppsLoader);
        static::assertSame('/path/to/app', $resolver->filesystem('TestApp')->location);
    }
}
