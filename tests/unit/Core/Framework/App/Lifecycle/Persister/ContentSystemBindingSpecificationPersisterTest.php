<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemBindingSpecificationPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\ResolvedBindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContentSystemBindingSpecificationPersister::class)]
class ContentSystemBindingSpecificationPersisterTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/_fixtures/binding-specifications';

    private IdsCollection $ids;

    private BindingSpecificationSerializer $serializer;

    private YamlBindingSpecificationLoader $loader;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->serializer = new BindingSpecificationSerializer();
        // Stub the validator: these tests exercise the persister's upsert/hash/delete logic, not binding
        // validation, and the fixture uses an unregistered type the dep-injected §6 constraint would reject
        // (and whose validator the default no-arg factory cannot build). Validation has its own tests.
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->loader = new YamlBindingSpecificationLoader([], $this->serializer, $validator);
    }

    #[TestDox('inserts a new binding, writing the serialized schema and content hash as the payload')]
    public function testInsertsNewBindingWritesExpectedPayload(): void
    {
        $repo = $this->createEmptyRepository();

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        $resolved = $this->loader->loadDtosFromDirectory(self::FIXTURES_DIR . '/Resources/content-system/binding-specifications', 'app:DemoApp');
        $normalized = $this->serializer->normalize($resolved[0]->dto);

        static::assertSame('from-media-library', $payload['name']);
        static::assertSame($this->ids->get('app'), $payload['appId']);
        static::assertIsString($payload['id']);
        static::assertSame($normalized, $payload['schema']);
        static::assertSame(Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR)), $payload['hash']);
    }

    #[TestDox('skips the upsert and never invalidates the cache when the stored hash matches the current file hash')]
    public function testSkipsUpsertWhenHashMatches(): void
    {
        $resolved = $this->loader->loadDtosFromDirectory(self::FIXTURES_DIR . '/Resources/content-system/binding-specifications', 'app:DemoApp');
        $normalized = $this->serializer->normalize($resolved[0]->dto);

        $seeded = $this->buildExistingEntity('binding-media', 'from-media-library');
        $seeded->setHash(Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR)));
        $seeded->setSchema($normalized);

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemBindingSpecificationCollection([$seeded]),
        ]);

        // No write means no cache invalidation: a stable install must not churn the registry cache.
        $registry = static::createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('updates the existing binding, reusing its id, when the hash changes')]
    public function testUpdatesExistingBindingWhenHashChanges(): void
    {
        $existing = $this->buildExistingEntity('binding-media', 'from-media-library');
        $existing->setHash('outdated-hash-value');

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemBindingSpecificationCollection([$existing]),
        ]);

        $registry = static::createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('binding-media'), $payload['id']);
        static::assertSame('from-media-library', $payload['name']);
        static::assertNotSame('outdated-hash-value', $payload['hash']);
    }

    #[TestDox('deletes stored bindings that the app no longer ships')]
    public function testDeletesBindingsNotPresentInFiles(): void
    {
        $obsolete = $this->buildExistingEntity('binding-old', 'legacy-binding');

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemBindingSpecificationCollection([$obsolete]),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('binding-old')]], $repo->deletes[0]);
    }

    #[TestDox('queries the stored bindings for the installing app by app id')]
    public function testQueriesExistingBindingsByAppId(): void
    {
        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([
            function (Criteria $criteria, Context $context): AppContentSystemBindingSpecificationCollection {
                static::assertCount(1, $criteria->getFilters());
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('appId', $filter->getField());
                static::assertSame($this->ids->get('app'), $filter->getValue());

                return new AppContentSystemBindingSpecificationCollection();
            },
        ]);

        $this->buildPersister($repo)->persist($this->buildContext());

        // Proves the flow ran through to the upsert, so the search callback above actually fired.
        static::assertCount(1, $repo->upserts);
    }

    #[TestDox('deletes all stored bindings and invalidates the cache when the app ships no YAML')]
    public function testDeletesAllAndInvalidatesWhenNoYaml(): void
    {
        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        $orphan = $this->buildExistingEntity('binding-orphan', 'orphan-binding');

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemBindingSpecificationCollection([$orphan])]);

        $registry = static::createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, loader: $loader, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('binding-orphan')]], $repo->deletes[0]);
    }

    #[TestDox('routes the upsert and delete through a single transaction')]
    public function testWritesGoThroughOneTransaction(): void
    {
        // The app ships from-media-library (an upsert) while a stored legacy-binding is no longer
        // shipped (a delete), so both write kinds run; the single transactional() call must wrap
        // them together.
        $obsolete = $this->buildExistingEntity('binding-old', 'legacy-binding');

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemBindingSpecificationCollection([$obsolete]),
        ]);

        $connection = static::createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (\Closure $work) {
                $work();

                return null;
            });

        $persister = $this->buildPersister($repo, connection: $connection);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        static::assertCount(1, $repo->deletes);
    }

    #[TestDox('returns early without writing when the app ships none and none are stored')]
    public function testEarlyReturnWhenBothEmpty(): void
    {
        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemBindingSpecificationCollection()]);

        $persister = $this->buildPersister($repo, loader: $loader);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('wraps a loader ContentSystemException as an AppException')]
    public function testThrowsAppExceptionWhenLoaderFails(): void
    {
        $loaderException = ContentSystemException::bindingSpecificationLoadFailed('from-media-library.yaml', 'Invalid YAML syntax');

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException($loaderException);

        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($repo, loader: $loader);

        try {
            $persister->persist($this->buildContext());
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_BINDING_SPECIFICATION_LOAD_FAILED, $e->getErrorCode());
            static::assertStringContainsString(ContentSystemBindingSpecificationPersister::DIRECTORY, $e->getMessage());
            static::assertSame($loaderException, $e->getPrevious());
        }
    }

    #[TestDox('wraps a unique-constraint violation from a concurrent install as an AppException')]
    public function testWrapsConcurrentDuplicateInstallAsAppException(): void
    {
        [$loader] = $this->buildResolvedLoader();

        $dbalException = static::createStub(UniqueConstraintViolationException::class);

        $emptyResult = new EntitySearchResult(
            'app_content_system_binding_specification',
            0,
            new AppContentSystemBindingSpecificationCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createStub(EntityRepository::class);
        $repo->method('search')->willReturn($emptyResult);
        $repo->method('upsert')->willThrowException($dbalException);

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);

        $persister = $this->buildPersisterWithRepository($repo, $loader, $registry);

        try {
            $persister->persist($this->buildContext());
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_BINDING_SPECIFICATION_DUPLICATE, $e->getErrorCode());
            static::assertStringContainsString('from-media-library', $e->getMessage());
            static::assertStringContainsString('app:DemoApp', $e->getMessage());
            static::assertSame($dbalException, $e->getPrevious());
        }
    }

    #[TestDox('skips cache invalidation when the delete fails inside the transaction')]
    public function testSkipsInvalidationWhenDeleteFailsInsideTransaction(): void
    {
        // A delete failure must propagate and leave the registry cache untouched, so the cache is
        // never refreshed from a write that did not commit. (Real rollback is a DB concern, covered
        // by integration.)
        [$loader] = $this->buildResolvedLoader();

        $obsolete = $this->buildExistingEntity('binding-old', 'legacy-binding');
        $existingResult = new EntitySearchResult(
            'app_content_system_binding_specification',
            1,
            new AppContentSystemBindingSpecificationCollection([$obsolete]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $deleteException = new \RuntimeException('delete failed mid-transaction');

        $repo = static::createStub(EntityRepository::class);
        $repo->method('search')->willReturn($existingResult);
        $repo->method('delete')->willThrowException($deleteException);

        $registry = static::createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersisterWithRepository($repo, $loader, $registry);

        $this->expectExceptionObject($deleteException);
        $persister->persist($this->buildContext());
    }

    private function buildExistingEntity(string $idKey, string $name): AppContentSystemBindingSpecificationEntity
    {
        $entity = new AppContentSystemBindingSpecificationEntity();
        $entity->setId($this->ids->create($idKey));
        $entity->setName($name);
        $entity->setHash('some-hash');
        $entity->setSchema([]);
        $entity->setAppId($this->ids->get('app'));

        return $entity;
    }

    /**
     * @param StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        ?YamlBindingSpecificationLoader $loader = null,
        ?AbstractContentSystemBindingSpecificationRegistry $registry = null,
        ?Connection $connection = null,
    ): ContentSystemBindingSpecificationPersister {
        $registry ??= static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);

        return new ContentSystemBindingSpecificationPersister(
            $loader ?? $this->loader,
            $repo,
            $this->serializer,
            $connection ?? $this->runTransactionStub(),
            $registry,
        );
    }

    /**
     * The two concurrency/rollback tests need a generic EntityRepository stub (willThrowException)
     * rather than the StaticEntityRepository buildPersister() is typed for.
     *
     * @param EntityRepository<AppContentSystemBindingSpecificationCollection> $repo
     */
    private function buildPersisterWithRepository(
        EntityRepository $repo,
        YamlBindingSpecificationLoader $loader,
        AbstractContentSystemBindingSpecificationRegistry $registry,
    ): ContentSystemBindingSpecificationPersister {
        return new ContentSystemBindingSpecificationPersister(
            $loader,
            $repo,
            $this->serializer,
            $this->runTransactionStub(),
            $registry,
        );
    }

    /**
     * @return array{YamlBindingSpecificationLoader, ResolvedBindingSpecificationDto}
     */
    private function buildResolvedLoader(): array
    {
        $resolved = new ResolvedBindingSpecificationDto(
            'from-media-library',
            'app:DemoApp',
            new BindingSpecificationDto('media-gallery', 'From media library', null, null),
        );

        $loader = static::createStub(YamlBindingSpecificationLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$resolved]);

        return [$loader, $resolved];
    }

    /**
     * A pass-through transaction for unit context: it runs the wrapped closure so the repository
     * writes still happen and their assertions hold. Real commit/rollback is exercised by the
     * integration suite.
     */
    private function runTransactionStub(): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static function (\Closure $work) {
            $work();

            return null;
        });

        return $connection;
    }

    private function buildContext(): AppPersistContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setName('DemoApp');

        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new Filesystem(self::FIXTURES_DIR),
            defaultLocale: 'en-GB',
        );
    }

    /**
     * @return StaticEntityRepository<AppContentSystemBindingSpecificationCollection>
     */
    private function createEmptyRepository(): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppContentSystemBindingSpecificationCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemBindingSpecificationCollection(),
        ]);

        return $repo;
    }
}
