<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
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
 *
 * @covers \Shopware\Administration\Snippet\AppAdministrationSnippetPersister
 */
class AppAdministrationSnippetPersisterTest extends TestCase
{
    /**
     * @dataProvider persisterDataProvider
     *
     * @param array<string, string> $snippets
     */
    public function testItPersistsSnippets(
        EntityRepository $appAdministrationSnippetRepository,
        EntityRepository $localeRepository,
        AppEntity $appEntity,
        array $snippets
    ): void {
        $persister = new AppAdministrationSnippetPersister(
            $appAdministrationSnippetRepository,
            $localeRepository
        );

        $persister->updateSnippets($appEntity, $snippets, Context::createDefaultContext());

        // assert no exception was thrown
        static::assertTrue(true);
    }

    /**
     * @dataProvider persisterExceptionDataProvider
     *
     * @param array<string, string> $snippets
     */
    public function testItPersistsSnippetsException(
        EntityRepository $appAdministrationSnippetRepository,
        EntityRepository $localeRepository,
        AppEntity $appEntity,
        array $snippets,
        string $expectedExceptionMessage
    ): void {
        $exceptionWasThrown = false;

        $persister = new AppAdministrationSnippetPersister(
            $appAdministrationSnippetRepository,
            $localeRepository
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
     * @return array<string, array{appAdministrationSnippetRepository: EntityRepository, localeRepository: EntityRepository, appEntity: AppEntity, newSnippets: array<string, string>}>
     */
    public function persisterDataProvider(): iterable
    {
        yield 'Test no new snippets, no deletions' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(),
            'localeRepository' => $this->getLocaleRepository(),
            'appEntity' => $this->getAppEntity(),
            'newSnippets' => [],
        ];

        yield 'Test new snippets, no deletion' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(
                [],
                [
                    [
                        'id' => 'snippetId',
                        'value' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
                        'appId' => 'appId',
                        'localeId' => 'en-GB',
                    ],
                ],
                []
            ),
            'localeRepository' => $this->getLocaleRepository([
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ]),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [
                'en-GB' => \json_encode(['my' => 'snippets'], \JSON_THROW_ON_ERROR),
            ],
        ];

        yield 'Test no new snippets, only deletions' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(
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
                ]
            ),
            'localeRepository' => $this->getLocaleRepository([
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ]),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [],
        ];

        yield 'Test new snippets and deletions' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(
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
                ]
            ),
            'localeRepository' => $this->getLocaleRepository([
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
                [
                    'id' => 'de-DE',
                    'code' => 'de-DE',
                ],
            ]),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [
                'en-GB' => \json_encode(['my' => 'added'], \JSON_THROW_ON_ERROR),
            ],
        ];

        yield 'Test update snippets' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(
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
                true // checks if snippets are updated (no new snippet id is used)
            ),
            'localeRepository' => $this->getLocaleRepository([
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ]),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [
                'en-GB' => \json_encode(['my' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
        ];
    }

    /**
     * @return array<string, array{appAdministrationSnippetRepository: EntityRepository, localeRepository: EntityRepository, appEntity: AppEntity, newSnippets: array<string, string>, exceptionMessage: string}>
     */
    public function persisterExceptionDataProvider(): iterable
    {
        yield 'Test it throws an exception when extending or overwriting the core' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(),
            'localeRepository' => $this->getLocaleRepository(),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [
                'en-GB' => \json_encode(['global' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            'exceptionMessage' => 'The following keys extend or overwrite the core snippets which is not allowed: global',
        ];

        yield 'Test it throws an exception when no en-GB is defined' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(),
            'localeRepository' => $this->getLocaleRepository(),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [
                'de-DE' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            'exceptionMessage' => 'The following snippet file must always be provided when providing snippets: en-GB',
        ];

        yield 'Test it throws an exception when the locale does not exists' => [
            'appAdministrationSnippetRepository' => $this->getAppAdministrationSnippetRepository(),
            'localeRepository' => $this->getLocaleRepository([
                [
                    'id' => 'en-GB',
                    'code' => 'en-GB',
                ],
            ]),
            'appEntity' => $this->getAppEntity('appId'),
            'newSnippets' => [
                'en-GB' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
                'foo-bar' => \json_encode(['myCustomSnippetName' => 'newTranslation'], \JSON_THROW_ON_ERROR),
            ],
            'exceptionMessage' => 'The locale foo-bar does not exists.',
        ];
    }

    private function getAppEntity(?string $appId = null): AppEntity
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
