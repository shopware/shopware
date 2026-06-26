<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemStyleOption\AppContentSystemStyleOptionCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemStyleOption\AppContentSystemStyleOptionEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemStyleOptionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\ResolvedStyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionCollisionDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ContentSystemStyleOptionPersister::class)]
class ContentSystemStyleOptionPersisterTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/_fixtures/style-options';

    private IdsCollection $ids;

    private StyleOptionSpecificationSerializer $serializer;

    private YamlStyleOptionLoader $loader;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->serializer = new StyleOptionSpecificationSerializer();
        $this->loader = new YamlStyleOptionLoader(
            $this->serializer,
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }

    #[TestDox('inserts a new option and queries existing then inactive options')]
    public function testInsertsNewOption(): void
    {
        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context): AppContentSystemStyleOptionCollection {
                static::assertCount(1, $criteria->getFilters());
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('appId', $filter->getField());

                return new AppContentSystemStyleOptionCollection();
            },
            static function (Criteria $criteria, Context $context): AppContentSystemStyleOptionCollection {
                $filters = $criteria->getFilters();
                static::assertCount(2, $filters);
                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('app.active', $filters[0]->getField());
                static::assertFalse($filters[0]->getValue());
                static::assertInstanceOf(NotFilter::class, $filters[1]);
                static::assertArrayHasKey('app', $criteria->getAssociations());

                return new AppContentSystemStyleOptionCollection();
            },
        ]);

        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame('brand-gap', $payload['name']);
        static::assertSame($this->ids->get('app'), $payload['appId']);
        static::assertIsString($payload['id']);
        static::assertIsArray($payload['schema']);
        static::assertIsString($payload['hash']);
    }

    #[TestDox('skips the upsert and never invalidates the cache when the stored hash matches the current file hash')]
    public function testSkipsUpsertWhenHashMatches(): void
    {
        $resolved = $this->loader->loadDtosFromDirectory(self::FIXTURES_DIR . '/Resources/content-system/style-options', 'app:DemoApp');
        $normalized = $this->serializer->normalize($resolved[0]->dto);

        $seeded = $this->buildExistingEntity('opt-gap', 'brand-gap');
        $seeded->setHash(Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR)));
        $seeded->setSchema($normalized);

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$seeded]),
            new AppContentSystemStyleOptionCollection(),
        ]);

        // No write means no cache invalidation: a stable install must not churn the registry cache.
        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('updates the existing option, reusing its id, when the hash changes')]
    public function testUpdatesExistingOptionWhenHashChanges(): void
    {
        $existing = $this->buildExistingEntity('opt-gap', 'brand-gap');
        $existing->setHash('outdated-hash-value');

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$existing]),
            new AppContentSystemStyleOptionCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('opt-gap'), $payload['id']);
        static::assertSame('brand-gap', $payload['name']);
        static::assertNotSame('outdated-hash-value', $payload['hash']);
    }

    #[TestDox('deletes stored options that the app no longer ships')]
    public function testDeletesOptionsNotPresentInFiles(): void
    {
        $obsolete = $this->buildExistingEntity('opt-old', 'legacy-option');

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$obsolete]),
            new AppContentSystemStyleOptionCollection(),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('opt-old')]], $repo->deletes[0]);
    }

    #[TestDox('returns early without writing when the app ships none and none are stored')]
    public function testEarlyReturnWhenBothEmpty(): void
    {
        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemStyleOptionCollection()]);

        $persister = $this->buildPersister($repo, loader: $loader);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('deletes all stored options and invalidates the cache when the app ships no YAML')]
    public function testDeletesAllAndInvalidatesWhenNoYaml(): void
    {
        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        $orphan = $this->buildExistingEntity('opt-orphan', 'orphan-option');

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemStyleOptionCollection([$orphan])]);

        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, loader: $loader, registry: $registry);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('opt-orphan')]], $repo->deletes[0]);
    }

    #[TestDox('wraps a loader ContentSystemException as an AppException')]
    public function testThrowsAppExceptionWhenLoaderFails(): void
    {
        $loaderException = ContentSystemException::styleOptionLoadFailed('brand-gap.yaml', 'Invalid YAML syntax');

        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException($loaderException);

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($repo, loader: $loader);

        try {
            $persister->persist($this->buildContext());
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_STYLE_OPTION_LOAD_FAILED, $e->getErrorCode());
            static::assertStringContainsString(ContentSystemStyleOptionPersister::STYLE_OPTIONS_DIRECTORY, $e->getMessage());
            static::assertSame($loaderException, $e->getPrevious());
        }
    }

    #[TestDox('completes the upsert when an inactive app option has no loaded app association')]
    public function testSkipsInactiveOptionWithNullAppDuringCollisionCheck(): void
    {
        $orphaned = new AppContentSystemStyleOptionEntity();
        $orphaned->setId($this->ids->create('orphaned-opt'));
        $orphaned->setName('unrelated-option');
        $orphaned->setHash('hash');
        $orphaned->setSchema([]);
        $orphaned->setAppId($this->ids->create('deleted-app'));
        // deliberately no setApp(): a dangling row whose app association did not load

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection(),
            new AppContentSystemStyleOptionCollection([$orphaned]),
        ]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        static::assertSame('brand-gap', $repo->upserts[0][0]['name']);
    }

    #[TestDox('fails hard when an option name collides with an already-registered option')]
    public function testThrowsWhenNameCollidesWithRegisteredOption(): void
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'brand-gap' => new StyleOptionSpecification('brand-gap', new StyleOptionValueType('integer', null, null, null, null), null, 'core'),
        ]);

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection(),
            new AppContentSystemStyleOptionCollection(),
        ]);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('brand-gap', 'core', 'app:DemoApp'));
        $persister->persist($this->buildContext());
    }

    #[TestDox('fails hard when an inactive app already occupies the option name')]
    public function testThrowsWhenInactiveAppOptionNameCollides(): void
    {
        $inactive = new AppContentSystemStyleOptionEntity();
        $inactive->setId($this->ids->create('inactive-opt'));
        $inactive->setName('brand-gap');
        $inactive->setHash('hash');
        $inactive->setSchema([]);
        $inactive->setAppId($this->ids->create('other-app'));

        $otherApp = new AppEntity();
        $otherApp->setId($this->ids->get('other-app'));
        $otherApp->setName('OtherApp');
        $inactive->setApp($otherApp);

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection(),
            new AppContentSystemStyleOptionCollection([$inactive]),
        ]);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('brand-gap', 'app:OtherApp', 'app:DemoApp'));
        $persister->persist($this->buildContext());
    }

    #[TestDox('wraps a unique-constraint violation from a concurrent install as an AppException')]
    public function testWrapsUniqueConstraintViolationAsAppException(): void
    {
        $resolved = new ResolvedStyleOptionSpecificationDto(
            'brand-gap',
            'app:DemoApp',
            new StyleOptionSpecificationDto('integer', null, null, null, null, null),
        );

        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$resolved]);

        $dbalException = static::createStub(UniqueConstraintViolationException::class);

        $emptyResult = new EntitySearchResult(
            'app_content_system_style_option',
            0,
            new AppContentSystemStyleOptionCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createStub(EntityRepository::class);
        $repo->method('search')->willReturn($emptyResult);
        $repo->method('upsert')->willThrowException($dbalException);

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);

        $persister = new ContentSystemStyleOptionPersister(
            $repo,
            $loader,
            new StyleOptionCollisionDetector($registry),
            $registry,
            $this->serializer,
            $this->runTransactionStub(),
        );

        try {
            $persister->persist($this->buildContext());
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_STYLE_OPTION_DUPLICATE, $e->getErrorCode());
            static::assertStringContainsString('brand-gap', $e->getMessage());
            static::assertStringContainsString('app:DemoApp', $e->getMessage());
            static::assertSame($dbalException, $e->getPrevious());
        }
    }

    #[TestDox('routes the upsert and delete through a single transaction')]
    public function testWritesGoThroughOneTransaction(): void
    {
        // The app ships brand-gap (an upsert) while a stored legacy-option is no longer shipped (a delete),
        // so both write kinds run; the single transactional() call must wrap them together.
        $obsolete = $this->buildExistingEntity('opt-old', 'legacy-option');

        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$obsolete]),
            new AppContentSystemStyleOptionCollection(),
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

    #[TestDox('skips cache invalidation when the delete fails inside the transaction')]
    public function testSkipsInvalidationWhenDeleteFailsInsideTransaction(): void
    {
        // A delete failure must propagate and leave the registry cache untouched, so the cache is never
        // refreshed from a write that did not commit. (Real rollback is a DB concern, covered by integration.)
        $resolved = new ResolvedStyleOptionSpecificationDto(
            'brand-gap',
            'app:DemoApp',
            new StyleOptionSpecificationDto('integer', null, null, null, null, null),
        );

        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([$resolved]);

        $obsolete = $this->buildExistingEntity('opt-old', 'legacy-option');
        $existingResult = new EntitySearchResult(
            'app_content_system_style_option',
            1,
            new AppContentSystemStyleOptionCollection([$obsolete]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $inactiveResult = new EntitySearchResult(
            'app_content_system_style_option',
            0,
            new AppContentSystemStyleOptionCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $deleteException = new \RuntimeException('delete failed mid-transaction');

        $repo = static::createStub(EntityRepository::class);
        $repo->method('search')->willReturnOnConsecutiveCalls($existingResult, $inactiveResult);
        $repo->method('delete')->willThrowException($deleteException);

        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);
        $registry->expects($this->never())->method('invalidate');

        $persister = new ContentSystemStyleOptionPersister(
            $repo,
            $loader,
            new StyleOptionCollisionDetector($registry),
            $registry,
            $this->serializer,
            $this->runTransactionStub(),
        );

        $this->expectExceptionObject($deleteException);
        $persister->persist($this->buildContext());
    }

    #[TestDox('revalidateForActivation passes when the activating app options do not collide with the registry')]
    public function testRevalidateForActivationPassesWithoutCollision(): void
    {
        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$this->buildExistingEntity('opt-gap', 'brand-gap')]),
            new AppContentSystemStyleOptionCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('all')->willReturn([]);

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->revalidateForActivation($this->buildActivationContext());
    }

    #[TestDox('revalidateForActivation fails hard when an activating option collides with a registered option')]
    public function testRevalidateForActivationThrowsOnRegistryCollision(): void
    {
        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$this->buildExistingEntity('opt-gap', 'brand-gap')]),
            new AppContentSystemStyleOptionCollection(),
        ]);

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'brand-gap' => new StyleOptionSpecification('brand-gap', new StyleOptionValueType('integer', null, null, null, null), null, 'core'),
        ]);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('brand-gap', 'core', 'app:DemoApp'));
        $persister->revalidateForActivation($this->buildActivationContext());
    }

    #[TestDox('revalidateForActivation does not self-collide on the activating app own now-active options')]
    public function testRevalidateForActivationToleratesOwnNowActiveOptions(): void
    {
        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection([$this->buildExistingEntity('opt-gap', 'brand-gap')]),
            new AppContentSystemStyleOptionCollection(),
        ]);

        // The rebuilt cache already counts the activating app's own option as registered; the excluded
        // source must keep that from reading as a self-collision.
        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->once())->method('all')->willReturn([
            'brand-gap' => new StyleOptionSpecification('brand-gap', new StyleOptionValueType('integer', null, null, null, null), null, 'app:DemoApp'),
        ]);

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->revalidateForActivation($this->buildActivationContext());
    }

    #[TestDox('revalidateForActivation skips the registry check when the app has no persisted options')]
    public function testRevalidateForActivationReturnsEarlyWithoutPersistedOptions(): void
    {
        /** @var StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemStyleOptionCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemStyleOptionRegistry::class);
        $registry->expects($this->never())->method('all');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->revalidateForActivation($this->buildActivationContext());
    }

    private function buildExistingEntity(string $idKey, string $name): AppContentSystemStyleOptionEntity
    {
        $entity = new AppContentSystemStyleOptionEntity();
        $entity->setId($this->ids->create($idKey));
        $entity->setName($name);
        $entity->setHash('some-hash');
        $entity->setSchema([]);
        $entity->setAppId($this->ids->get('app'));

        return $entity;
    }

    /**
     * @param StaticEntityRepository<AppContentSystemStyleOptionCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        ?YamlStyleOptionLoader $loader = null,
        ?AbstractContentSystemStyleOptionRegistry $registry = null,
        ?Connection $connection = null,
    ): ContentSystemStyleOptionPersister {
        if ($registry === null) {
            $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
            $registry->method('all')->willReturn([]);
        }

        return new ContentSystemStyleOptionPersister(
            $repo,
            $loader ?? $this->loader,
            new StyleOptionCollisionDetector($registry),
            $registry,
            $this->serializer,
            $connection ?? $this->runTransactionStub(),
        );
    }

    /**
     * A pass-through transaction for unit context: it runs the wrapped closure so the repository writes
     * still happen and their assertions hold. Real commit/rollback is exercised by the integration suite.
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

    private function buildActivationContext(): AppActivationContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setName('DemoApp');

        return new AppActivationContext($app, Context::createDefaultContext());
    }
}
