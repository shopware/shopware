<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetCollection;
use Shopware\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\ContentSystemLayoutPresetPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetPersister::class)]
class ContentSystemLayoutPresetPersisterTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    #[TestDox('inserts a new preset with the normalized authoring data as schema')]
    public function testInsertsNewPresetWhenNoneExist(): void
    {
        $dto = $this->dto('Hero');

        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context): AppContentSystemLayoutPresetCollection {
                static::assertCount(1, $criteria->getFilters());
                $filter = $criteria->getFilters()[0];
                static::assertInstanceOf(EqualsFilter::class, $filter);
                static::assertSame('appId', $filter->getField());

                return new AppContentSystemLayoutPresetCollection();
            },
        ]);

        $persister = $this->buildPersister($repo, $this->dtoLoader(['DemoApp:Hero' => $dto]));
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame('DemoApp:Hero', $payload['name']);
        static::assertSame($this->normalize($dto), $payload['schema']);
        static::assertSame($this->ids->get('app'), $payload['appId']);
        static::assertIsString($payload['id']);
        static::assertIsString($payload['hash']);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('updates an existing preset when its hash changes, reusing the row id')]
    public function testUpdatesExistingPresetWhenHashChanges(): void
    {
        $existing = $this->existingEntity('preset-hero', 'DemoApp:Hero', 'outdated-hash');

        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemLayoutPresetCollection([$existing]),
        ]);

        $persister = $this->buildPersister($repo, $this->dtoLoader(['DemoApp:Hero' => $this->dto('Hero')]));
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('preset-hero'), $payload['id']);
        static::assertSame('DemoApp:Hero', $payload['name']);
        static::assertNotSame('outdated-hash', $payload['hash']);
    }

    #[TestDox('skips the upsert when the stored hash matches the current data')]
    public function testSkipsUpsertWhenHashMatches(): void
    {
        $dto = $this->dto('Hero');

        $existing = $this->existingEntity('preset-hero', 'DemoApp:Hero', $this->hashOf($dto));

        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemLayoutPresetCollection([$existing]),
        ]);

        $persister = $this->buildPersister($repo, $this->dtoLoader(['DemoApp:Hero' => $dto]));
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('deletes stored presets that are no longer shipped by the app, and invalidates the registry')]
    public function testDeletesPresetsNotPresentInFiles(): void
    {
        $obsolete = $this->existingEntity('preset-old', 'DemoApp:Old', 'hash');

        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemLayoutPresetCollection([$obsolete]),
        ]);

        $registry = static::createMock(AbstractContentSystemLayoutPresetRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $persister = $this->buildPersister($repo, $this->dtoLoader([]), $registry);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertCount(1, $repo->deletes);
        static::assertSame([['id' => $this->ids->get('preset-old')]], $repo->deletes[0]);
    }

    #[TestDox('upserts only the changed preset when multiple exist and one hash matches')]
    public function testUpsertsOnlyChangedPresetWhenMultipleExist(): void
    {
        $unchanged = $this->dto('Hero');

        $existingHero = $this->existingEntity('preset-hero', 'DemoApp:Hero', $this->hashOf($unchanged));
        $existingBanner = $this->existingEntity('preset-banner', 'DemoApp:Banner', 'outdated-hash');

        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemLayoutPresetCollection([$existingHero, $existingBanner]),
        ]);

        $registry = static::createMock(AbstractContentSystemLayoutPresetRegistry::class);
        $registry->expects($this->once())->method('invalidate');

        $loader = $this->dtoLoader([
            'DemoApp:Hero' => $unchanged,
            'DemoApp:Banner' => $this->dto('Banner'),
        ]);

        $persister = $this->buildPersister($repo, $loader, $registry);
        $persister->persist($this->buildContext());

        static::assertCount(1, $repo->upserts);
        $payload = $repo->upserts[0][0];

        static::assertSame($this->ids->get('preset-banner'), $payload['id']);
        static::assertSame('DemoApp:Banner', $payload['name']);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('returns early without touching the repository when the app ships and stores no presets')]
    public function testEarlyReturnWhenBothEmpty(): void
    {
        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([
            new AppContentSystemLayoutPresetCollection(),
        ]);

        $registry = static::createMock(AbstractContentSystemLayoutPresetRegistry::class);
        $registry->expects($this->never())->method('invalidate');

        $persister = $this->buildPersister($repo, $this->dtoLoader([]), $registry);
        $persister->persist($this->buildContext());

        static::assertSame([], $repo->upserts);
        static::assertSame([], $repo->deletes);
    }

    #[TestDox('wraps a loader ContentSystemException as an AppException')]
    public function testThrowsAppExceptionWhenLoaderFails(): void
    {
        $loaderException = ContentSystemException::layoutPresetLoadFailed('hero.yaml', 'Invalid YAML syntax');

        $loader = static::createStub(YamlLayoutPresetLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException($loaderException);

        /** @var StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($repo, $loader);

        $this->expectExceptionObject(
            AppException::contentSystemLayoutPresetLoadFailed('Resources/content-system/presets', $loaderException->getMessage(), $loaderException)
        );
        $persister->persist($this->buildContext());
    }

    #[TestDox('wraps a UniqueConstraintViolationException as an AppException on concurrent id collision')]
    public function testWrapsUniqueConstraintViolationAsAppException(): void
    {
        $loader = $this->dtoLoader(['DemoApp:Hero' => $this->dto('Hero')]);

        $dbalException = static::createStub(UniqueConstraintViolationException::class);

        $emptyResult = new EntitySearchResult(
            'app_content_system_layout_preset',
            0,
            new AppContentSystemLayoutPresetCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repo = static::createStub(EntityRepository::class);
        $repo->method('search')->willReturn($emptyResult);
        $repo->method('upsert')->willThrowException($dbalException);

        $persister = new ContentSystemLayoutPresetPersister(
            $repo,
            $loader,
            new LayoutPresetSpecificationSerializer(),
            static::createStub(AbstractContentSystemLayoutPresetRegistry::class),
        );

        try {
            $persister->persist($this->buildContext());
            static::fail('Expected AppException was not thrown');
        } catch (AppException $e) {
            static::assertSame(AppException::CONTENT_SYSTEM_LAYOUT_PRESET_DUPLICATE, $e->getErrorCode());
            static::assertStringContainsString('DemoApp:Hero', $e->getMessage());
            static::assertStringContainsString('app:DemoApp', $e->getMessage());
            static::assertSame($dbalException, $e->getPrevious());
        }
    }

    private function dto(string $name): LayoutPresetSpecificationDto
    {
        return new LayoutPresetSpecificationDto($name, 'A preset.', 'regular-star', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(LayoutPresetSpecificationDto $dto): array
    {
        return (new LayoutPresetSpecificationSerializer())->normalize($dto);
    }

    private function hashOf(LayoutPresetSpecificationDto $dto): string
    {
        return Hasher::hash(json_encode($this->normalize($dto), \JSON_THROW_ON_ERROR));
    }

    private function existingEntity(string $idKey, string $name, string $hash): AppContentSystemLayoutPresetEntity
    {
        $entity = new AppContentSystemLayoutPresetEntity();
        $entity->setId($this->ids->create($idKey));
        $entity->setName($name);
        $entity->setSchema([]);
        $entity->setHash($hash);
        $entity->setAppId($this->ids->get('app'));

        return $entity;
    }

    /**
     * @param array<string, LayoutPresetSpecificationDto> $dtos
     */
    private function dtoLoader(array $dtos): YamlLayoutPresetLoader
    {
        $loader = static::createStub(YamlLayoutPresetLoader::class);
        $loader->method('loadDtosFromDirectory')->willReturn($dtos);

        return $loader;
    }

    /**
     * @param StaticEntityRepository<AppContentSystemLayoutPresetCollection> $repo
     */
    private function buildPersister(
        StaticEntityRepository $repo,
        YamlLayoutPresetLoader $loader,
        ?AbstractContentSystemLayoutPresetRegistry $registry = null,
    ): ContentSystemLayoutPresetPersister {
        return new ContentSystemLayoutPresetPersister(
            $repo,
            $loader,
            new LayoutPresetSpecificationSerializer(),
            $registry ?? static::createStub(AbstractContentSystemLayoutPresetRegistry::class),
        );
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
            appFilesystem: new Filesystem(sys_get_temp_dir()),
            defaultLocale: 'en-GB',
        );
    }
}
