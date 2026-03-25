<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypePersister::class)]
class ContentSystemElementTypePersisterTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    private IdsCollection $ids;

    private ElementTypeSpecificationSerializer $serializer;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->serializer = new ElementTypeSpecificationSerializer();
    }

    #[TestDox('inserts a new element type when no existing types are stored')]
    public function testInsertsNewTypeWhenNoneExist(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame('App:Demo:Hero', $payload['name']);
        static::assertTrue($payload['active']);
        static::assertSame($this->ids->get('app'), $payload['appId']);
        static::assertArrayHasKey('id', $payload);
        static::assertArrayHasKey('schema', $payload);
        static::assertArrayHasKey('hash', $payload);
    }

    #[TestDox('skips the upsert when the stored hash already matches the current file hash')]
    public function testSkipsUpsertWhenHashMatches(): void
    {
        $normalized = $this->computeNormalizedForFixture();
        $hash = Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR));

        $existing = new AppContentSystemElementTypeEntity();
        $existing->setId($this->ids->create('type-hero'));
        $existing->setName('App:Demo:Hero');
        $existing->setHash($hash);
        $existing->setSchema($normalized);
        $existing->setAppId($this->ids->get('app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection([$existing])]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('updates an existing type when its hash has changed')]
    public function testUpdatesExistingTypeWhenHashChanges(): void
    {
        $existing = new AppContentSystemElementTypeEntity();
        $existing->setId($this->ids->create('type-hero'));
        $existing->setName('App:Demo:Hero');
        $existing->setHash('outdated-hash-value');
        $existing->setSchema([]);
        $existing->setAppId($this->ids->get('app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection([$existing])]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('type-hero'), $payload['id']);
        static::assertSame('App:Demo:Hero', $payload['name']);
        static::assertNotSame('outdated-hash-value', $payload['hash']);
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
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection([$obsolete])]);

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('type-old')]], $repo->deletes[0]);
    }

    #[TestDox('returns early when the types directory does not exist in the app filesystem')]
    public function testReturnsEarlyWhenTypesDirectoryDoesNotExist(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext(new StaticFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('returns early when the types directory contains no YAML files')]
    public function testReturnsEarlyWhenNoYamlFilesFound(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $filesystem = new StaticFilesystem(['Resources/content-system/types' => '']);

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($filesystem));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('skips files whose parsed YAML content is not an array')]
    public function testSkipsNonArrayYamlContent(): void
    {
        // StaticFilesystem has() checks keys — provide the directory so has() returns true.
        // We override findFiles() indirectly: since StaticFilesystem always returns [] from
        // findFiles(), we cannot exercise the "non-array YAML" path via StaticFilesystem.
        // Instead, we stub the filesystem via an anonymous subclass.
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $scalarYamlFs = new class extends Filesystem {
            public function __construct()
            {
                parent::__construct('/stub');
            }

            public function has(string ...$path): bool
            {
                return true;
            }

            public function findFiles(string $name, string $in): array
            {
                $info = $this->createSplFileInfo();

                return [$info];
            }

            public function read(string ...$path): string
            {
                // A YAML scalar (not an array)
                return 'just-a-string';
            }

            private function createSplFileInfo(): SplFileInfo
            {
                return new SplFileInfo(
                    '/stub/Resources/content-system/types/scalar.yaml',
                    'Resources/content-system/types',
                    'scalar.yaml',
                );
            }
        };

        $persister = $this->buildPersister($repo);
        $persister->persist($this->buildContext($scalarYamlFs));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('throws when the validator returns constraint violations for a type DTO')]
    public function testThrowsOnValidationFailure(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name must not be blank', null, [], null, 'name', ''),
        ]);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $persister = $this->buildPersister($repo, validator: $validator);

        $this->expectExceptionObject(AppException::elementTypeInvalid('App:Demo:Hero', $violations));
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    #[TestDox('throws when the type name collides with a core or plugin type in the registry')]
    public function testThrowsOnCollisionWithCoreType(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $registry = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectExceptionObject(AppException::elementTypeCollision('App:Demo:Hero', 'core/plugin', 'app'));
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    #[TestDox('throws when the type name is already registered by a different app in the database')]
    public function testThrowsOnCollisionWithOtherApp(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('OtherApp');

        $persister = $this->buildPersister($repo, connection: $connection);

        $this->expectExceptionObject(AppException::elementTypeCollision('App:Demo:Hero', 'OtherApp', 'app'));
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    /**
     * @param StaticEntityRepository<AppContentSystemElementTypeCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        ?ValidatorInterface $validator = null,
        ?AbstractContentSystemElementTypeRegistry $registry = null,
        ?Connection $connection = null,
    ): ContentSystemElementTypePersister {
        if ($validator === null) {
            $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        }

        if ($registry === null) {
            $registry = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
            $registry->method('has')->willReturn(false);
        }

        if ($connection === null) {
            $connection = $this->createStub(Connection::class);
            $connection->method('fetchOne')->willReturn(false);
        }

        return new ContentSystemElementTypePersister(
            $repo,
            $this->serializer,
            $validator,
            $registry,
            $connection,
        );
    }

    private function buildContext(Filesystem $filesystem): AppLifecycleContext
    {
        $app = new AppEntity();
        $app->setId($this->ids->get('app'));
        $app->setActive(true);

        return new AppLifecycleContext(
            manifest: $this->createStub(Manifest::class),
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

    /**
     * @return array<string, mixed>
     */
    private function computeNormalizedForFixture(): array
    {
        $content = $this->buildRealFilesystem()->read('Resources/content-system/types/hero.yaml');
        $data = Yaml::parse($content);
        static::assertIsArray($data);
        $dto = $this->serializer->denormalize($data);

        return $this->serializer->normalize($dto);
    }
}
