<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(AppStorage::class)]
class AppStorageTest extends TestCase
{
    public function testFindById(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([[$app]]);

        static::assertSame($app, (new AppStorage($repository))->findById('app-id', Context::createDefaultContext()));
    }

    public function testFindByName(): void
    {
        $app = AppFixture::createAppEntity(name: 'SwagTest');
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([[$app]]);

        static::assertSame($app, (new AppStorage($repository))->findByName('SwagTest', Context::createDefaultContext()));
    }

    public function testReturnsNullIfAppDoesNotExist(): void
    {
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([
            [],
            [],
        ]);
        $appStorage = new AppStorage($repository);

        static::assertNull($appStorage->findById('missing-id', Context::createDefaultContext()));
        static::assertNull($appStorage->findByName('missing-name', Context::createDefaultContext()));
    }

    public function testFindAll(): void
    {
        $apps = new AppCollection([AppFixture::createAppEntity()]);
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([$apps]);

        static::assertSame($apps, (new AppStorage($repository))->findAll(Context::createDefaultContext()));
    }

    public function testFindAllByNameOrLabel(): void
    {
        $apps = new AppCollection([AppFixture::createAppEntity()]);
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([$apps]);

        static::assertSame($apps, (new AppStorage($repository))->findAllWithNameOrLabel('test-app', Context::createDefaultContext()));
    }
}
