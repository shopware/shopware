<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\NoDatabaseSourceResolver;
use Shopware\Core\Framework\App\Source\Source;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SourceResolver::class)]
class SourceResolverTest extends TestCase
{
    public function testResolveSourceTypeThrowsExceptionWhenNoSourceSupports(): void
    {
        static::expectException(AppException::class);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $resolver = new SourceResolver([], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        $app = $this->createMock(Manifest::class);

        $resolver->resolveSourceType($app);
    }

    public function testCanResolveManifestToType(): void
    {
        $app = $this->createMock(Manifest::class);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $resolver = new SourceResolver([new SupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame('supporting-source', $resolver->resolveSourceType($app));
    }

    public function testFilesystemForManifestThrowsExceptionWhenNoSourceSupportsIt(): void
    {
        static::expectException(AppException::class);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $resolver = new SourceResolver([new NonSupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        $app = $this->createMock(Manifest::class);

        $resolver->filesystemForManifest($app);
    }

    public function testFilesystemForManifest(): void
    {
        $app = $this->createMock(Manifest::class);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $resolver = new SourceResolver([new SupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame('/', $resolver->filesystemForManifest($app)->location);
    }

    public function testFilesystemForAppThrowsExceptionWhenNoSourceSupportsIt(): void
    {
        static::expectException(AppException::class);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $resolver = new SourceResolver([new NonSupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');

        $resolver->filesystemForApp($app);
    }

    public function testFilesystemForApp(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $resolver = new SourceResolver([new SupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame('/', $resolver->filesystemForApp($app)->location);
    }

    public function testFilesystemForAppCacheHit(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $fs = new Filesystem('/');
        $sourceMock = $this->createMock(Source::class);
        $sourceMock->expects(static::once())
            ->method('filesystem')
            ->with($app)
            ->willReturn($fs);
        $sourceMock->expects(static::once())
            ->method('supports')
            ->with($app)
            ->willReturn(true);

        $resolver = new SourceResolver([$sourceMock], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame($fs, $resolver->filesystemForApp($app));
        // Second call should return the same instance and not call the source
        static::assertSame($fs, $resolver->filesystemForApp($app));
    }

    public function testFilesystemForAppCacheMiss(): void
    {
        $firstApp = new AppEntity();
        $firstApp->setId(Uuid::randomHex());
        $firstApp->setName('TestApp');
        $firstApp->setVersion('1.0.0');

        $secondApp = new AppEntity();
        $secondApp->setId(Uuid::randomHex());
        $secondApp->setName('TestApp');
        $secondApp->setVersion('2.0.0');

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $firstFs = new Filesystem('/one/');
        $secondFs = new Filesystem('/two/');

        $sourceMock = $this->createMock(Source::class);
        $sourceMock->expects(static::exactly(2))
            ->method('filesystem')
            ->willReturnOnConsecutiveCalls($firstFs, $secondFs);
        $sourceMock->expects(static::exactly(2))
            ->method('supports')
            ->willReturn(true);

        $resolver = new SourceResolver([$sourceMock], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame($firstFs, $resolver->filesystemForApp($firstApp));
        static::assertSame($secondFs, $resolver->filesystemForApp($secondApp));
    }

    public function testFilesystemForAppCacheResets(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $fs = new Filesystem('/');
        $sourceMock = $this->createMock(Source::class);
        $sourceMock->expects(static::exactly(2))
            ->method('filesystem')
            ->with($app)
            ->willReturn($fs);
        $sourceMock->expects(static::exactly(2))
            ->method('supports')
            ->with($app)
            ->willReturn(true);
        $sourceMock->expects(static::once())
            ->method('reset')
            ->with([$fs]);

        $resolver = new SourceResolver([$sourceMock], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame($fs, $resolver->filesystemForApp($app));
        $resolver->reset();
        static::assertSame($fs, $resolver->filesystemForApp($app));
    }

    public function testFilesystemForAppNameThrowsExceptionWhenAppDoesNotExist(): void
    {
        static::expectException(AppException::class);

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([new AppCollection()]);

        $resolver = new SourceResolver([new NonSupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        $resolver->filesystemForAppName('my-app');
    }

    public function testFilesystemForAppNameThrowsExceptionWhenNoSourceSupports(): void
    {
        static::expectException(AppException::class);

        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([new AppCollection([$app])]);

        $resolver = new SourceResolver([new NonSupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        $resolver->filesystemForApp($app);
    }

    public function testFilesystemForAppName(): void
    {
        $app = new AppEntity();
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setVersion('1.0.0');

        /** @var StaticEntityRepository<AppCollection> $repo */
        $repo = new StaticEntityRepository([new AppCollection([$app])]);

        $resolver = new SourceResolver([new SupportingSource()], $repo, $this->createMock(NoDatabaseSourceResolver::class));

        static::assertSame('/', $resolver->filesystemForAppName($app->getName())->location);
    }

    public function testFilesystemForAppNameUsesActiveAppLoaderWhenNoDatabaseIsPresent(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(static::once())
            ->method('search')
            ->willThrowException(new ConnectionException($this->createMock(DriverException::class), null));

        $fs = new StaticFilesystem();
        $noDbResolver = $this->createMock(NoDatabaseSourceResolver::class);
        $noDbResolver->expects(static::once())
            ->method('filesystem')
            ->with('TestApp')
            ->willReturn($fs);

        $resolver = new SourceResolver([new SupportingSource()], $repo, $noDbResolver);

        static::assertSame($fs, $resolver->filesystemForAppName('TestApp'));
    }
}

/**
 * @internal
 */
#[Package('core')]
class SupportingSource implements Source
{
    public static function name(): string
    {
        return 'supporting-source';
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return true;
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        return new Filesystem('/');
    }

    public function reset(array $filesystems): void
    {
    }
}

/**
 * @internal
 */
#[Package('core')]
class NonSupportingSource implements Source
{
    public static function name(): string
    {
        return 'nonsupporting-source';
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return false;
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        return new Filesystem('/');
    }

    public function reset(array $filesystems): void
    {
    }
}
