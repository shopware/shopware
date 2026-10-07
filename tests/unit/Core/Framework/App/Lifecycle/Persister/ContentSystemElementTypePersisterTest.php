<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ResolvedElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\CopilotSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('inserts new element type with correct payload')]
    public function testInsertsNewTypeWhenNoneExist(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context): AppContentSystemElementTypeCollection {
                static::assertCount(1, $criteria->getFilters());
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('appId', $filter->getField());

                return new AppContentSystemElementTypeCollection();
            },
            static function (Criteria $criteria, Context $context): AppContentSystemElementTypeCollection {
                $filters = $criteria->getFilters();
                static::assertCount(2, $filters);
                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('app.active', $filters[0]->getField());
                static::assertInstanceOf(NotFilter::class, $filters[1]);
                static::assertArrayHasKey('app', $criteria->getAssociations());

                return new AppContentSystemElementTypeCollection();
            },
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame('DemoApp:Hero', $payload['name']);
        static::assertSame($this->ids->get('app'), $payload['appId']);
        static::assertIsString($payload['id']);
        static::assertIsArray($payload['schema']);
        static::assertIsString($payload['hash']);
    }

    #[TestDox('updates existing type when hash changes')]
    public function testUpdatesExistingTypeWhenHashChanges(): void
    {
        $existing = $this->buildExistingEntity('type-hero', 'DemoApp:Hero');
        $existing->setHash('outdated-hash-value');

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
        $spec = $this->loader->loadDtosFromDirectory(
            self::FIXTURES_DIR . '/Resources/content-system/types',
            'app:DemoApp',
            'DemoApp',
        );

        $hash = Hasher::hash(json_encode($this->serializer->normalize($spec[0]->dto), \JSON_THROW_ON_ERROR));

        $seeded = $this->buildExistingEntity('type-hero', 'DemoApp:Hero');
        $seeded->setHash($hash);
        $seeded->setSchema($this->serializer->normalize($spec[0]->dto));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$seeded]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('deletes stored types that are no longer present in the app filesystem')]
    public function testDeletesTypesNotPresentInFiles(): void
    {
        $obsolete = $this->buildExistingEntity('type-old', 'App:Old:Type');

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$obsolete]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('type-old')]], $repo->deletes[0]);
    }

    #[TestDox('upserts only the changed type when multiple types exist and one hash matches')]
    public function testUpsertsOnlyChangedTypeWhenMultipleTypesExist(): void
    {
        $unchangedDto = new ElementTypeSpecificationDto(
            label: 'Unchanged Hero',
            description: 'Hero that stays the same',
            icon: null,
            category: null,
            copilot: new CopilotSpecificationDto(summary: 'unchanged hero', hints: []),
            properties: [],
            slots: [],
        );

        $changedDto = new ElementTypeSpecificationDto(
            label: 'Changed Banner',
            description: 'Banner that will be updated',
            icon: null,
            category: null,
            copilot: new CopilotSpecificationDto(summary: 'changed banner', hints: []),
            properties: [],
            slots: [],
        );

        $resolvedUnchanged = new ResolvedElementTypeSpecificationDto(
            name: 'DemoApp:Hero',
            source: 'app:DemoApp',
            dto: $unchangedDto,
        );

        $resolvedChanged = new ResolvedElementTypeSpecificationDto(
            name: 'DemoApp:Banner',
            source: 'app:DemoApp',
            dto: $changedDto,
        );

        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$resolvedUnchanged, $resolvedChanged]);

        $matchingHash = Hasher::hash(json_encode($this->serializer->normalize($unchangedDto), \JSON_THROW_ON_ERROR));

        $existingHero = $this->buildExistingEntity('type-hero', 'DemoApp:Hero');
        $existingHero->setHash($matchingHash);
        $existingHero->setSchema($this->serializer->normalize($unchangedDto));

        $existingBanner = $this->buildExistingEntity('type-banner', 'DemoApp:Banner');
        $existingBanner->setHash('outdated-hash');

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$existingHero, $existingBanner]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, loader: $loader, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('type-banner'), $payload['id']);
        static::assertSame('DemoApp:Banner', $payload['name']);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('deletes all existing types and invalidates cache when app ships no YAML files')]
    public function testDeletesAllTypesWhenYamlRemoved(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        $orphan = $this->buildExistingEntity('type-orphan', 'DemoApp:Orphan');

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$orphan]),
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, loader: $loader, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('type-orphan')]], $repo->deletes[0]);
    }

    #[TestDox('routes the upsert and delete through a single transaction')]
    public function testWritesGoThroughOneTransaction(): void
    {
        $obsolete = $this->buildExistingEntity('type-old', 'DemoApp:Legacy');

        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection([$obsolete]),
            new AppContentSystemElementTypeCollection(),
        ]);

        $connection = static::createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (\Closure $work) use ($repo) {
                static::assertSame([], $repo->upserts);
                static::assertSame([], $repo->deletes);

                $work();

                static::assertCount(1, $repo->upserts);
                static::assertCount(1, $repo->deletes);

                return null;
            });

        $this->buildPersister($repo, connection: $connection)
            ->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        static::assertCount(1, $repo->deletes);
    }

    #[TestDox('holds the per-app lock from the existing-state read through cache invalidation')]
    public function testHoldsLockAroundReconciliationAndInvalidation(): void
    {
        $lockHeld = false;

        $lock = static::createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(true)->willReturnCallback(
            static function () use (&$lockHeld): bool {
                $lockHeld = true;

                return true;
            }
        );
        $lock->expects($this->once())->method('release')->willReturnCallback(
            static function () use (&$lockHeld): void {
                static::assertTrue($lockHeld);
                $lockHeld = false;
            }
        );

        $lockFactory = static::createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('content_system_element_type_persist_' . $this->ids->get('app'), 15.0)
            ->willReturn($lock);

        $repo = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context) use (&$lockHeld): AppContentSystemElementTypeCollection {
                static::assertTrue($lockHeld);

                return new AppContentSystemElementTypeCollection();
            },
            static function (Criteria $criteria, Context $context) use (&$lockHeld): AppContentSystemElementTypeCollection {
                static::assertTrue($lockHeld);

                return new AppContentSystemElementTypeCollection();
            },
        ]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->expects($this->once())->method('all')->willReturnCallback(
            static function () use (&$lockHeld): array {
                static::assertTrue($lockHeld);

                return [];
            }
        );
        $registry->expects($this->once())->method('invalidate')->willReturnCallback(
            static function () use (&$lockHeld): void {
                static::assertTrue($lockHeld);
            }
        );

        $this->buildPersister($repo, registry: $registry, lockFactory: $lockFactory)
            ->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertFalse($lockHeld);
    }

    #[TestDox('releases the lock and leaves the cache untouched when the transaction fails')]
    public function testReleasesLockWhenTransactionFails(): void
    {
        $failure = new \RuntimeException('transaction failed');

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willThrowException($failure);

        $lock = static::createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(true)->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->never())->method('invalidate');

        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
            new AppContentSystemElementTypeCollection(),
        ]);

        $this->expectExceptionObject($failure);
        $this->buildPersister(
            $repo,
            registry: $registry,
            connection: $connection,
            lockFactory: $lockFactory,
        )->persist($this->buildContext($this->buildRealFilesystem()));
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

        $persister = $this->buildPersister($repo, loader: $loader);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('skips inactive app type with null app entity during collision check')]
    public function testSkipsInactiveTypeWithNullAppDuringCollisionCheck(): void
    {
        $orphanedEntity = new AppContentSystemElementTypeEntity();
        $orphanedEntity->setId($this->ids->create('orphaned-type'));
        $orphanedEntity->setName('DemoApp:Hero');
        $orphanedEntity->setHash('hash');
        $orphanedEntity->setSchema([]);
        $orphanedEntity->setAppId($this->ids->create('deleted-app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemElementTypeCollection(),
            new AppContentSystemElementTypeCollection([$orphanedEntity]),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        static::assertSame('DemoApp:Hero', $repo->upserts[0][0]['name']);
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

    #[TestDox('throws when type name collides with already registered type')]
    public function testThrowsWhenTypeNameCollidesWithRegisteredType(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([
            'DemoApp:Hero' => new ContentSystemElementTypeSpecification(
                'DemoApp:Hero',
                'Hero',
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

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('DemoApp:Hero', 'core', 'app:DemoApp')
        );
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    #[TestDox('wraps UniqueConstraintViolationException as AppException on concurrent name collision')]
    public function testWrapsUniqueConstraintViolationAsAppException(): void
    {
        $resolvedDto = new ResolvedElementTypeSpecificationDto(
            name: 'DemoApp:Hero',
            source: 'app:DemoApp',
            dto: new ElementTypeSpecificationDto(
                label: 'Hero',
                description: 'test',
                icon: null,
                category: null,
                copilot: new CopilotSpecificationDto(summary: 'test', hints: []),
                properties: [],
                slots: [],
            ),
        );

        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$resolvedDto]);

        $dbalException = static::createStub(UniqueConstraintViolationException::class);

        $emptyResult = new EntitySearchResult(
            'app_content_system_element_type',
            0,
            new AppContentSystemElementTypeCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createStub(EntityRepository::class);
        $repo->method('search')->willReturn($emptyResult);
        $repo->method('upsert')->willThrowException($dbalException);

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);

        $persister = new ContentSystemElementTypePersister(
            $repo,
            $loader,
            new ElementTypeCollisionDetector($registry),
            $registry,
            $this->serializer,
            $this->runTransactionStub(),
            new LockFactory(new InMemoryStore()),
        );

        try {
            $persister->persist($this->buildContext($this->buildRealFilesystem()));
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_ELEMENT_TYPE_DUPLICATE, $e->getErrorCode());
            static::assertStringContainsString('DemoApp:Hero', $e->getMessage());
            static::assertStringContainsString('app:DemoApp', $e->getMessage());
            static::assertSame($dbalException, $e->getPrevious());
        }
    }

    #[TestDox('throws collision exception when inactive app occupies the same type name')]
    public function testThrowsWhenInactiveAppTypeNameCollides(): void
    {
        $inactiveEntity = new AppContentSystemElementTypeEntity();
        $inactiveEntity->setId($this->ids->create('inactive-type'));
        $inactiveEntity->setName('DemoApp:Hero');
        $inactiveEntity->setHash('hash');
        $inactiveEntity->setSchema([]);
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

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('DemoApp:Hero', 'app:OtherApp', 'app:DemoApp')
        );
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    private function buildExistingEntity(string $idKey, string $name): AppContentSystemElementTypeEntity
    {
        $entity = new AppContentSystemElementTypeEntity();
        $entity->setId($this->ids->create($idKey));
        $entity->setName($name);
        $entity->setHash('some-hash');
        $entity->setSchema([]);
        $entity->setAppId($this->ids->get('app'));

        return $entity;
    }

    /**
     * @param StaticEntityRepository<AppContentSystemElementTypeCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        ?YamlTypeLoader $loader = null,
        ?AbstractContentSystemElementTypeRegistry $registry = null,
        ?Connection $connection = null,
        ?LockFactory $lockFactory = null,
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
            $connection ?? $this->runTransactionStub(),
            $lockFactory ?? new LockFactory(new InMemoryStore()),
        );
    }

    private function runTransactionStub(): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static function (\Closure $work) {
            $work();

            return null;
        });

        return $connection;
    }

    private function buildContext(Filesystem $filesystem): AppPersistContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setName('DemoApp');

        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $filesystem,
            defaultLocale: 'en-GB',
        );
    }

    private function buildRealFilesystem(): Filesystem
    {
        return new Filesystem(self::FIXTURES_DIR);
    }
}
