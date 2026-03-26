<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypePersister::class)]
class ContentSystemElementTypePersisterTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/_fixtures';

    private IdsCollection $ids;

    private ElementTypeSpecificationSerializer $serializer;

    private YamlTypeLoader $loader;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->serializer = new ElementTypeSpecificationSerializer();
        $this->loader = new YamlTypeLoader(
            $this->serializer,
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            new ElementTypeNameResolver(),
        );
    }

    #[TestDox('inserts new element type with correct payload and invalidates registry')]
    public function testInsertsNewTypeWhenNoneExist(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
            new AppContentSystemElementTypeCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame('DemoApp:Hero', $payload['name']);
        static::assertTrue($payload['active']);
        static::assertSame($this->ids->get('app'), $payload['appId']);
        static::assertArrayHasKey('id', $payload);
        static::assertArrayHasKey('schema', $payload);
        static::assertArrayHasKey('hash', $payload);
    }

    #[TestDox('updates existing type when hash changes')]
    public function testUpdatesExistingTypeWhenHashChanges(): void
    {
        $existing = new AppContentSystemElementTypeEntity();
        $existing->setId($this->ids->create('type-hero'));
        $existing->setName('DemoApp:Hero');
        $existing->setHash('outdated-hash-value');
        $existing->setSchema([]);
        $existing->setAppId($this->ids->get('app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$existing]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('type-hero'), $payload['id']);
        static::assertSame('DemoApp:Hero', $payload['name']);
        static::assertNotSame('outdated-hash-value', $payload['hash']);
    }

    #[TestDox('skips upsert when stored hash matches current file hash')]
    public function testSkipsUpsertWhenHashMatches(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
            new AppContentSystemElementTypeCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $firstPassPayload = $repo->upserts[0][0];

        $seeded = new AppContentSystemElementTypeEntity();
        $seeded->setId($firstPassPayload['id']);
        $seeded->setName($firstPassPayload['name']);
        $seeded->setHash($firstPassPayload['hash']);
        $seeded->setSchema($firstPassPayload['schema']);
        $seeded->setAppId($firstPassPayload['appId']);

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo2 */
        $repo2 = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$seeded]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $registry2 = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry2->method('all')->willReturn([]);
        $registry2->expects($this->never())->method('invalidate');

        $persister2 = $this->buildPersister($repo2, registry: $registry2);
        $persister2->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo2->upserts);
        static::assertSame([], $repo2->deletes);
    }

    #[TestDox('deletes stored types that are no longer present in the app filesystem')]
    public function testDeletesTypesNotPresentInFiles(): void
    {
        $obsolete = new AppContentSystemElementTypeEntity();
        $obsolete->setId($this->ids->create('type-old'));
        $obsolete->setName('App:Old:Type');
        $obsolete->setHash('some-hash');
        $obsolete->setSchema([]);
        $obsolete->setAppId($this->ids->get('app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$obsolete]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('type-old')]], $repo->deletes[0]);
    }

    #[TestDox('deletes all existing types when app ships no YAML files')]
    public function testDeletesAllTypesWhenYamlRemoved(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        $orphan = new AppContentSystemElementTypeEntity();
        $orphan->setId($this->ids->create('type-orphan'));
        $orphan->setName('DemoApp:Orphan');
        $orphan->setHash('some-hash');
        $orphan->setSchema([]);
        $orphan->setAppId($this->ids->get('app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$orphan]),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, loader: $loader, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('type-orphan')]], $repo->deletes[0]);
    }

    #[TestDox('returns early when loader returns empty and no existing types exist')]
    public function testEarlyReturnWhenBothEmpty(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersister($repo, loader: $loader, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('throws AppException when loader fails with ContentSystemException')]
    public function testThrowsAppExceptionWhenLoaderFails(): void
    {
        $loaderException = ContentSystemException::elementTypeLoadFailed('hero.yaml', 'Invalid YAML syntax');

        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException($loaderException);

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($repo, loader: $loader);

        $this->expectExceptionObject(
            AppException::contentSystemElementTypeLoadFailed('Resources/content-system/types', $loaderException->getMessage(), $loaderException)
        );
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    #[TestDox('lets ContentSystemException from collision detector propagate')]
    public function testCollisionPropagation(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([
            'DemoApp:Hero' => new ContentSystemElementTypeSpecification(
                'DemoApp:Hero',
                'Hero',
                'test',
                'test',
                null,
                null,
                new CopilotSpecification('test', []),
                [],
                [],
                'core',
            ),
        ]);

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
            new AppContentSystemElementTypeCollection(),
        ]);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectException(ContentSystemException::class);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    #[TestDox('passes inactive app type names to collision detector for validation')]
    public function testInactiveAppTypeCollision(): void
    {
        $inactiveEntity = new AppContentSystemElementTypeEntity();
        $inactiveEntity->setId($this->ids->create('inactive-type'));
        $inactiveEntity->setName('DemoApp:Hero');
        $inactiveEntity->setHash('hash');
        $inactiveEntity->setSchema([]);
        $inactiveEntity->setActive(false);
        $inactiveEntity->setAppId($this->ids->create('other-app'));

        $otherApp = new AppEntity();
        $otherApp->setId($this->ids->get('other-app'));
        $otherApp->setName('OtherApp');
        $inactiveEntity->setApp($otherApp);

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
            new AppContentSystemElementTypeCollection([$inactiveEntity]),
        ]);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectException(ContentSystemException::class);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    /**
     * @param StaticEntityRepository<AppContentSystemElementTypeCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        ?YamlTypeLoader $loader = null,
        ?AbstractContentSystemElementTypeRegistry $registry = null,
    ): ContentSystemElementTypePersister {
        if ($registry === null) {
            $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
            $registry->method('all')->willReturn([]);
        }

        $detector = new ElementTypeCollisionDetector($registry);

        return new ContentSystemElementTypePersister(
            $repo,
            $loader ?? $this->loader,
            $detector,
            $registry,
            $this->serializer,
        );
    }

    private function buildContext(Filesystem $filesystem): AppLifecycleContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setName('DemoApp');
        $app->setActive(true);

        return new AppLifecycleContext(
            manifest: static::createStub(Manifest::class),
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $filesystem,
            defaultLocale: 'en-GB',
            isInstall: true,
        );
    }

    private function buildRealFilesystem(): Filesystem
    {
        return new Filesystem(self::FIXTURES_DIR);
    }
}
