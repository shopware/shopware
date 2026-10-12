<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Administration\Snippet\CachedSnippetFinder;
use Shopware\Administration\Snippet\SnippetException;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AppAdministrationSnippetPersister::class)]
class AppAdministrationSnippetPersisterTest extends TestCase
{
    /**
     * @param list<array{id: string, localeId: string}> $existingSnippets
     * @param list<string> $localeCodes
     * @param array<string, string> $snippets
     * @param list<string> $expectedUpsertIds ids of the upserted rows, 'new' for a generated id
     * @param list<array{value: string, appId: string, localeId: string}> $expectedUpserts
     * @param list<string> $expectedDeletedIds
     */
    #[DataProvider('persisterDataProvider')]
    public function testItPersistsSnippets(
        array $existingSnippets,
        array $localeCodes,
        array $snippets,
        array $expectedUpsertIds,
        array $expectedUpserts,
        array $expectedDeletedIds,
    ): void {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([CachedSnippetFinder::CACHE_TAG]);

        $snippetRepository = $this->getAppAdministrationSnippetRepository($existingSnippets);

        $persister = new AppAdministrationSnippetPersister(
            $snippetRepository,
            $this->getLocaleRepository($localeCodes),
            $cacheInvalidator,
            new Filesystem()
        );

        $persister->updateSnippets(self::getAppEntity('appId'), $snippets, Context::createDefaultContext());

        static::assertCount(1, $snippetRepository->upserts);
        $upserts = $snippetRepository->upserts[0];
        static::assertSame(
            $expectedUpsertIds,
            array_map(static fn (array $upsert): string => Uuid::isValid($upsert['id']) ? 'new' : $upsert['id'], $upserts)
        );
        static::assertSame(
            $expectedUpserts,
            array_map(static fn (array $upsert): array => array_diff_key($upsert, ['id' => true]), $upserts)
        );
        static::assertSame(
            [array_map(static fn (string $id): array => ['id' => $id], $expectedDeletedIds)],
            $snippetRepository->deletes
        );
    }

    public function testItPersistsSnippetsWithoutCoreAdministrationSnippets(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('readFile')->willThrowException(new IOException('File not found'));
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([CachedSnippetFinder::CACHE_TAG]);

        $persister = new AppAdministrationSnippetPersister(
            $this->getAppAdministrationSnippetRepository(),
            $this->getLocaleRepository(),
            $cacheInvalidator,
            $filesystem
        );

        $persister->updateSnippets(self::getAppEntity(), [], Context::createDefaultContext());
    }

    public function testItPersistsSnippetsWithInvalidCoreAdministrationSnippets(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('readFile')->willReturn('invalid json');
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $this->expectExceptionObject(new \JsonException('Syntax error', 4));

        $persister = new AppAdministrationSnippetPersister(
            $this->getAppAdministrationSnippetRepository(),
            $this->getLocaleRepository(),
            $cacheInvalidator,
            $filesystem
        );

        $persister->updateSnippets(self::getAppEntity(), [], Context::createDefaultContext());
    }

    /**
     * @param array<string, string> $snippets
     */
    #[DataProvider('persisterExceptionDataProvider')]
    public function testItPersistsSnippetsException(
        array $snippets,
        SnippetException $expectedException
    ): void {
        $persister = new AppAdministrationSnippetPersister(
            $this->getAppAdministrationSnippetRepository(),
            $this->getLocaleRepository(),
            static::createStub(CacheInvalidator::class),
            new Filesystem()
        );

        $this->expectExceptionObject($expectedException);
        $persister->updateSnippets(self::getAppEntity('appId'), $snippets, Context::createDefaultContext());
    }

    public function testSkipsSnippetsForNonExistingLocale(): void
    {
        $snippetRepository = new StaticEntityRepository([
            new AppAdministrationSnippetCollection([
                (new AppAdministrationSnippetEntity())->assign(['id' => 'snippet-id', 'localeId' => 'en-GB', 'appId' => 'app-id']),
            ]),
        ]);
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([
                (new LocaleEntity())->assign(['id' => 'en-GB', 'code' => 'en-GB']),
                (new LocaleEntity())->assign(['id' => 'de-DE', 'code' => 'de-DE']),
            ]),
        ]);

        $persister = new AppAdministrationSnippetPersister(
            $snippetRepository,
            $localeRepository,
            static::createStub(CacheInvalidator::class),
            new Filesystem()
        );

        $persister->updateSnippets(
            self::getAppEntity('app-id'),
            [
                'en-GB' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
                'non-existing-locale' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
            ],
            Context::createDefaultContext()
        );

        static::assertCount(1, $snippetRepository->upserts);
        static::assertSame([
            'id' => 'snippet-id',
            'value' => '{"my":"snippets"}',
            'appId' => 'app-id',
            'localeId' => 'en-GB',
        ], $snippetRepository->upserts[0][0]);
    }

    /**
     * @return iterable<string, array{existingSnippets: list<array{id: string, localeId: string}>, localeCodes: list<string>, snippets: array<string, string>, expectedUpsertIds: list<string>, expectedUpserts: list<array{value: string, appId: string, localeId: string}>, expectedDeletedIds: list<string>}>
     */
    public static function persisterDataProvider(): iterable
    {
        yield 'no new snippets, no deletions' => [
            'existingSnippets' => [],
            'localeCodes' => [],
            'snippets' => [],
            'expectedUpsertIds' => [],
            'expectedUpserts' => [],
            'expectedDeletedIds' => [],
        ];

        yield 'new snippets, no deletion' => [
            'existingSnippets' => [],
            'localeCodes' => ['en-GB'],
            'snippets' => ['en-GB' => '{"my":"snippets"}'],
            'expectedUpsertIds' => ['new'],
            'expectedUpserts' => [['value' => '{"my":"snippets"}', 'appId' => 'appId', 'localeId' => 'en-GB']],
            'expectedDeletedIds' => [],
        ];

        yield 'no new snippets, only deletions' => [
            'existingSnippets' => [['id' => 'snippetId', 'localeId' => 'en-GB']],
            'localeCodes' => ['en-GB'],
            'snippets' => [],
            'expectedUpsertIds' => [],
            'expectedUpserts' => [],
            'expectedDeletedIds' => ['snippetId'],
        ];

        yield 'new snippets and deletions' => [
            'existingSnippets' => [['id' => 'snippetToDelete', 'localeId' => 'de-DE']],
            'localeCodes' => ['en-GB', 'de-DE'],
            'snippets' => ['en-GB' => '{"my":"added"}'],
            'expectedUpsertIds' => ['new'],
            'expectedUpserts' => [['value' => '{"my":"added"}', 'appId' => 'appId', 'localeId' => 'en-GB']],
            'expectedDeletedIds' => ['snippetToDelete'],
        ];

        yield 'existing snippets are updated in place' => [
            'existingSnippets' => [['id' => 'oldSnippetId', 'localeId' => 'en-GB']],
            'localeCodes' => ['en-GB'],
            'snippets' => ['en-GB' => '{"my":"newTranslation"}'],
            'expectedUpsertIds' => ['oldSnippetId'],
            'expectedUpserts' => [['value' => '{"my":"newTranslation"}', 'appId' => 'appId', 'localeId' => 'en-GB']],
            'expectedDeletedIds' => [],
        ];
    }

    /**
     * @return iterable<string, array{array<mixed>, SnippetException}>
     */
    public static function persisterExceptionDataProvider(): iterable
    {
        yield 'Test it throws an exception when extending or overwriting the core' => [
            [
                'en-GB' => \json_encode(['global' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            SnippetException::extendOrOverwriteCore(['global']),
        ];

        yield 'Test it throws an exception when no en-GB is defined' => [
            [
                'de-DE' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            SnippetException::defaultLanguageNotGiven('en-GB'),
        ];
    }

    private static function getAppEntity(?string $appId = null): AppEntity
    {
        $appEntity = new AppEntity();

        $appEntity->setId($appId ?? Uuid::randomHex());

        return $appEntity;
    }

    /**
     * @param list<array{id: string, localeId: string}> $existingSnippets
     *
     * @return StaticEntityRepository<AppAdministrationSnippetCollection>
     */
    private function getAppAdministrationSnippetRepository(array $existingSnippets = []): StaticEntityRepository
    {
        return new StaticEntityRepository([
            new AppAdministrationSnippetCollection(array_map(
                static fn (array $snippet): AppAdministrationSnippetEntity => (new AppAdministrationSnippetEntity())->assign([...$snippet, 'appId' => 'appId']),
                $existingSnippets
            )),
        ]);
    }

    /**
     * @param list<string> $localeCodes
     *
     * @return StaticEntityRepository<LocaleCollection>
     */
    private function getLocaleRepository(array $localeCodes = []): StaticEntityRepository
    {
        return new StaticEntityRepository([
            new LocaleCollection(array_map(
                static fn (string $code): LocaleEntity => (new LocaleEntity())->assign(['id' => $code, 'code' => $code]),
                $localeCodes
            )),
        ]);
    }
}
