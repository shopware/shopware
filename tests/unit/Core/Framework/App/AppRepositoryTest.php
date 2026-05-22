<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(AppRepository::class)]
class AppRepositoryTest extends TestCase
{
    public function testFindById(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([[$app]]);

        static::assertSame($app, (new AppRepository($repository))->findById('app-id', Context::createDefaultContext()));
    }

    public function testFindByName(): void
    {
        $app = AppFixture::createAppEntity(name: 'SwagTest');
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([[$app]]);

        static::assertSame($app, (new AppRepository($repository))->findByName('SwagTest', Context::createDefaultContext()));
    }

    public function testReturnsNullIfAppDoesNotExist(): void
    {
        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([
            [],
            [],
        ]);
        $appRepository = new AppRepository($repository);

        static::assertNull($appRepository->findById('missing-id', Context::createDefaultContext()));
        static::assertNull($appRepository->findByName('missing-name', Context::createDefaultContext()));
    }
}
