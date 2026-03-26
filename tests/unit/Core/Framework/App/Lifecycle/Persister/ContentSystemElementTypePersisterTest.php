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
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;

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
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
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
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection([$existing])]);

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
        $normalized = $this->computeNormalizedForFixture();
        $hash = Hasher::hash(json_encode($normalized, \JSON_THROW_ON_ERROR));

        $existing = new AppContentSystemElementTypeEntity();
        $existing->setId($this->ids->create('type-hero'));
        $existing->setName('DemoApp:Hero');
        $existing->setHash($hash);
        $existing->setSchema($normalized);
        $existing->setAppId($this->ids->get('app'));

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection([$existing])]);

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
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

        $registry = static::createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, registry: $registry);
        $persister->persist($this->buildContext($this->buildRealFilesystem()));

        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('type-old')]], $repo->deletes[0]);
    }

    #[TestDox('skips persistence when loader returns empty list')]
    public function testSkipsPersistenceWhenLoaderReturnsEmpty(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn([]);

        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($repo, loader: $loader);
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

    #[TestDox('throws when the type name collides with a core or plugin type in the registry')]
    public function testThrowsOnCollisionWithCoreType(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);

        $persister = $this->buildPersister($repo, registry: $registry);

        $this->expectExceptionObject(AppException::contentSystemElementTypeCollision('DemoApp:Hero', 'core/plugin', 'app'));
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    #[TestDox('throws when the type name is already registered by a different app in the database')]
    public function testThrowsOnCollisionWithOtherApp(): void
    {
        /** @var StaticEntityRepository<AppContentSystemElementTypeCollection> $repo */
        $repo = new StaticEntityRepository([new AppContentSystemElementTypeCollection()]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('OtherApp');

        $persister = $this->buildPersister($repo, connection: $connection);

        $this->expectExceptionObject(AppException::contentSystemElementTypeCollision('DemoApp:Hero', 'OtherApp', 'app'));
        $persister->persist($this->buildContext($this->buildRealFilesystem()));
    }

    /**
     * @param StaticEntityRepository<AppContentSystemElementTypeCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        ?AbstractContentSystemElementTypeRegistry $registry = null,
        ?Connection $connection = null,
        ?YamlTypeLoader $loader = null,
    ): ContentSystemElementTypePersister {
        if ($registry === null) {
            $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
            $registry->method('has')->willReturn(false);
        }

        if ($connection === null) {
            $connection = static::createStub(Connection::class);
            $connection->method('fetchOne')->willReturn(false);
        }

        return new ContentSystemElementTypePersister(
            $repo,
            $this->serializer,
            $registry,
            $connection,
            $loader ?? $this->loader,
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

    /**
     * @return array<string, mixed>
     */
    private function computeNormalizedForFixture(): array
    {
        $content = $this->buildRealFilesystem()->read('Resources/content-system/types', 'hero.yaml');
        $data = Yaml::parse($content);

        if (!\is_array($data)) {
            throw new \UnexpectedValueException('Expected array from YAML parse');
        }

        $dto = $this->serializer->denormalize($data);

        return $this->serializer->normalize($dto);
    }
}
