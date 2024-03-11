<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Administration\Snippet\CachedSnippetFinder;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;

/**
 * @internal
 */
#[CoversClass(AppAdministrationSnippetPersister::class)]
class AppAdministrationSnippetPersisterTest extends TestCase
{
    /**
     * @param array<mixed> $snippetData
     * @param array<mixed> $localeData
     * @param array<string, string> $snippets
     */
    #[DataProvider('persisterDataProvider')]
    public function testItPersistsSnippets(
        array $snippetData,
        array $localeData,
        AppEntity $appEntity,
        array $snippets
    ): void {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects(static::once())
            ->method('invalidate')
            ->with([CachedSnippetFinder::CACHE_TAG]);

        $persister = new AppAdministrationSnippetPersister(
            $this->getAppAdministrationSnippetRepository(...$snippetData),
            $this->getLocaleRepository($localeData),
            $cacheInvalidator
        );

        $persister->updateSnippets($appEntity, $snippets, Context::createDefaultContext());
    }

    /**
     * @param array<mixed> $localeData
     * @param array<string, string> $snippets
     */
    #[DataProvider('persisterExceptionDataProvider')]
    public function testItPersistsSnippetsException(
        array $localeData,
        AppEntity $appEntity,
        array $snippets,
        string $expectedExceptionMessage
    ): void {
        $exceptionWasThrown = false;

        $persister = new AppAdministrationSnippetPersister(
            $this->getAppAdministrationSnippetRepository(),
            $this->getLocaleRepository($localeData),
            $this->createMock(CacheInvalidator::class)
        );

        try {
            $persister->updateSnippets($appEntity, $snippets, Context::createDefaultContext());
        } catch (\Exception $exception) {
            static::assertEquals($expectedExceptionMessage, $exception->getMessage());

            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Expected exception with the following message to be thrown: ' . $expectedExceptionMessage);
        }
    }

    /**
     * @return array<string, array{array<mixed>, array<mixed>, AppEntity, array<string, string>}>
     */
    public static function persisterDataProvider(): iterable
    {
        yield 'Test no new snippets, no deletions' => [
            [],
            [],
            self::getAppEntity(),
            [],
        ];

        yield 'Test new snippets, no deletion' => [
            [
                [],
                [
                    [
                        'id' => 'snippetId',
                        'value' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'en-GB',
                    ],
                ],
            ],
            [
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ],
            self::getAppEntity('appId'),
            [
                'en-GB' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
            ],
        ];

        yield 'Test no new snippets, only deletions' => [
            [
                [
                    [
                        'id' => 'snippetId',
                        'value' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'en-GB',
                    ],
                ],
                [],
                [
                    ['id' => 'snippetId'],
                ],
            ],
            [
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ],
            self::getAppEntity('appId'),
            [],
        ];

        yield 'Test new snippets and deletions' => [
            [
                [
                    [
                        'id' => 'snippetToDelete',
                        'value' => \json_encode(['my' => 'deleted'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'de-DE',
                    ],
                ],
                [
                    [
                        'id' => 'snippetToAdd',
                        'value' => \json_encode(['my' => 'added'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'en-GB',
                    ],
                ],
                [
                    ['id' => 'snippetToDelete'],
                ],
            ],
            [
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
                [
                    'id' => 'de-DE',
                    'code' => 'de-DE',
                ],
            ],
            self::getAppEntity('appId'),
            [
                'en-GB' => \json_encode(['my' => 'added'], \JSON_THROW_ON_ERROR),
            ],
        ];

        yield 'Test update snippets' => [
            [
                [
                    [
                        'id' => 'oldSnippetId',
                        'value' => \json_encode(['my' => 'oldTranslation'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'en-GB',
                    ],
                ],
                [
                    [
                        'id' => 'oldSnippetId',
                        'value' => \json_encode(['my' => 'newTranslation'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'en-GB',
                    ],
                ],
                [],
                true, // checks if snippets are updated (no new snippet id is used)
            ],
            [
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ],
            self::getAppEntity('appId'),
            [
                'en-GB' => \json_encode(['my' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
        ];
    }

    /**
     * @return array<string, array{array<mixed>, AppEntity, array<string, string>, string}>
     */
    public static function persisterExceptionDataProvider(): iterable
    {
        yield 'Test it throws an exception when extending or overwriting the core' => [
            [],
            self::getAppEntity('appId'),
            [
                'en-GB' => \json_encode(['global' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            'The following keys extend or overwrite the core snippets which is not allowed: global',
        ];

        yield 'Test it throws an exception when no en-GB is defined' => [
            [],
            self::getAppEntity('appId'),
            [
                'de-DE' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            'The following snippet file must always be provided when providing snippets: en-GB',
        ];

        yield 'Test it throws an exception when the locale does not exists' => [
            [
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ],
            self::getAppEntity('appId'),
            [
                'en-GB' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
                'foo-bar' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            'The locale foo-bar does not exists.',
        ];
    }

    private static function getAppEntity(?string $appId = null): AppEntity
    {
        $appEntity = new AppEntity();

        $appEntity->setId($appId ?? Uuid::randomHex());

        return $appEntity;
    }

    /**
     * @param array<int, array<string, string>> $snippetsFromApp
     * @param array<int, array<string, string>> $newSnippets
     * @param array<int, array<string, string>> $deletesSnippetIds
     */
    private function getAppAdministrationSnippetRepository(array $snippetsFromApp = [], array $newSnippets = [], array $deletesSnippetIds = [], bool $updatedSnippets = false): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);

        $appSnippets = [];
        foreach ($snippetsFromApp as $snippet) {
            $appSnippet = new AppAdministrationSnippetEntity();
            $appSnippet->assign($snippet);

            $appSnippets[] = $appSnippet;
        }

        $collection = new AppAdministrationSnippetCollection($appSnippets);
        $entitySearchResult = new EntitySearchResult(
            AppAdministrationSnippetDefinition::ENTITY_NAME,
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repository
            ->method('search')
            ->willReturn($entitySearchResult);

        if ($updatedSnippets) {
            $repository
                ->method('upsert')
                ->with($newSnippets, Context::createDefaultContext());
        }

        if ($newSnippets && !$updatedSnippets) {
            $repository
                ->method('upsert')
                // assert at least count($newSnippets) are upserted
                ->with(static::arrayHasKey(\count($newSnippets) - 1), Context::createDefaultContext());
        } elseif (!$updatedSnippets) {
            $repository
                ->method('upsert')
                ->with([], Context::createDefaultContext());
        }

        $repository
            ->method('delete')
            ->with($deletesSnippetIds, Context::createDefaultContext());

        return $repository;
    }

    /**
     * @param array<int, array{id: string, code: string}> $locales
     */
    private function getLocaleRepository(array $locales = []): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);

        $localeEntities = [];
        foreach ($locales as $locale) {
            $localeEntity = new LocaleEntity();
            $localeEntity->assign($locale);

            $localeEntities[] = $localeEntity;
        }

        $collection = new LocaleCollection($localeEntities);
        $entitySearchResult = new EntitySearchResult(
            LocaleDefinition::ENTITY_NAME,
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $repository
            ->method('search')
            ->willReturn($entitySearchResult);

        return $repository;
    }
}
