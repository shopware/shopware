<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
#[CoversClass(CategoryRoute::class)]
class CategoryRouteTest extends TestCase
{
    private const LANGUAGE_IDS = [
        'en' => Defaults::LANGUAGE_SYSTEM,
        'de' => '20354d7ae4fe47af8ff6187bc0dedede',
        'at' => '20354d7ae4fe47af8ff6187bc0aaaaaa',
    ];

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    /**
     * @return iterable<array{
     *      languageCodeChain: non-empty-list<string>,
     *      expected: string
     *  }>
     */
    public static function categoryCmsSlotMergeDataProvider(): iterable
    {
        yield 'EN config' => [
            'languageCodeChain' => ['en'],
            'expected' => 'en config',
        ];

        yield 'EN/DE config' => [
            'languageCodeChain' => ['en', 'de'],
            'expected' => 'de config',
        ];

        yield 'EN/AT config' => [
            'languageCodeChain' => ['en', 'at'],
            'expected' => 'at config',
        ];

        yield 'EN/DE/AT config' => [
            'languageCodeChain' => ['en', 'de', 'at'],
            'expected' => 'at config',
        ];
    }

    /**
     * @param non-empty-list<string> $languageCodeChain
     */
    #[DataProvider('categoryCmsSlotMergeDataProvider')]
    public function testCategoryCmsSlotMerge(array $languageCodeChain, string $expected): void
    {
        $request = new Request();
        $salesChannelContext = $this->buildSalesChannelContext($languageCodeChain);
        $category = $this->buildPageCategory($languageCodeChain);
        $cmsPage = $this->buildCmsPage();

        $cmsPageLoaderCriteria = new Criteria([$this->ids->get('cms-page')]);
        $cmsPageLoaderCriteria->setTitle('category::cms-page');

        $categoryRepositoryMock = $this->createMock(SalesChannelRepository::class);
        $categoryRepositoryMock
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                1,
                new CategoryCollection([$category]),
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            ));

        $cmsPageLoader = $this->createMock(SalesChannelCmsPageLoaderInterface::class);

        // Passively asserts that the call of the cmsPageLoader mock using the slotConfig has been successful
        $cmsPageLoader
            ->expects($this->once())
            ->method('load')
            ->with(
                $request,
                $cmsPageLoaderCriteria,
                $salesChannelContext,
                [
                    'content' => [
                        'field' => [
                            'value' => $expected,
                        ],
                    ],
                ],
                new EntityResolverContext($salesChannelContext, $request, new CategoryDefinition(), $category),
            )->willReturn(new EntitySearchResult(
                'cms_page',
                1,
                new CmsPageCollection([$cmsPage]),
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            ));

        $categoryRoute = new CategoryRoute(
            $categoryRepositoryMock,
            $cmsPageLoader,
            new EntityCmsSlotConfigInheritanceBuilder(
                $this->createConnectionWithParentLanguageIds($languageCodeChain),
            ),
            new CategoryDefinition(),
            static::createStub(CacheTagCollector::class),
        );

        $categoryRoute->load(
            $this->ids->get('category'),
            $request,
            $salesChannelContext,
        );
    }

    public function testLoadLinkPageType(): void
    {
        $request = new Request();
        $salesChannelContext = $this->buildSalesChannelContext(['en']);
        $category = $this->buildPageCategory(['en']);

        $category->setType(CategoryDefinition::TYPE_LINK);

        $response = $this->buildContentlessCategoryRepositoryMock(
            $category,
            $salesChannelContext,
            $request
        );

        // Passively asserts that no exception has been thrown
        static::assertSame(CategoryDefinition::TYPE_LINK, $response->getCategory()->getType());
    }

    public function testLoadFolderPageType(): void
    {
        $request = new Request();
        $salesChannelContext = $this->buildSalesChannelContext(['en']);
        $category = $this->buildPageCategory(['en']);

        $category->setType(CategoryDefinition::TYPE_FOLDER);

        $this->expectExceptionObject(new CategoryNotFoundException($this->ids->get('category')));

        $this->buildContentlessCategoryRepositoryMock(
            $category,
            $salesChannelContext,
            $request
        );
    }

    public function testHomeRouteIsTaggedWithNavigationCategoryId(): void
    {
        $request = new Request();
        $category = $this->buildPageCategory(['en']);
        $cmsPage = $this->buildCmsPage();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setNavigationCategoryId($category->getId());
        $salesChannel->setNavigationCategoryDepth(2);
        $salesChannel->setTaxCalculationType(Generator::TAX_CALCULATION_TYPE);
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannelContext = Generator::generateSalesChannelContext(
            new Context(new SalesChannelApiSource(Uuid::randomHex()), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]),
            salesChannel: $salesChannel,
        );

        $categoryRepository = $this->createMock(SalesChannelRepository::class);
        $categoryRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                1,
                new CategoryCollection([$category]),
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            ));

        $cmsPageLoader = static::createStub(SalesChannelCmsPageLoaderInterface::class);
        $cmsPageLoader->method('load')->willReturn(new EntitySearchResult(
            'cms_page',
            1,
            new CmsPageCollection([$cmsPage]),
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(CategoryRoute::buildName($category->getId()));

        $categoryRoute = new CategoryRoute(
            $categoryRepository,
            $cmsPageLoader,
            new EntityCmsSlotConfigInheritanceBuilder($this->createConnectionWithParentLanguageIds(['en'])),
            new CategoryDefinition(),
            $cacheTagCollector,
        );

        $categoryRoute->load(CategoryRoute::HOME, $request, $salesChannelContext);
    }

    private function buildCmsPage(): CmsPageEntity
    {
        $cmsSlot = new CmsSlotEntity();
        $cmsSlot->setId($this->ids->create('slot'));
        $cmsSlot->setUniqueIdentifier('slot');
        $cmsSlot->setSlot('content');

        $cmsBlock = new CmsBlockEntity();
        $cmsBlock->setUniqueIdentifier('block');
        $cmsBlock->setSlots(new CmsSlotCollection([$cmsSlot]));

        $cmsSection = new CmsSectionEntity();
        $cmsSection->setUniqueIdentifier('section');
        $cmsSection->setBlocks(new CmsBlockCollection([$cmsBlock]));

        $cmsPage = new CmsPageEntity();
        $cmsPage->setId($this->ids->get('cms-page'));
        $cmsPage->setUniqueIdentifier('cms-page');
        $cmsPage->setType('landingpage');
        $cmsPage->setSections(new CmsSectionCollection([$cmsSection]));

        return $cmsPage;
    }

    /**
     * @param non-empty-list<string> $languageCodeChain
     */
    private function buildSalesChannelContext(array $languageCodeChain): SalesChannelContext
    {
        $languageIdChain = \array_map(
            static fn (string $languageCode) => self::LANGUAGE_IDS[$languageCode],
            \array_reverse($languageCodeChain),
        );

        return Generator::generateSalesChannelContext(new Context(
            new SalesChannelApiSource(Uuid::randomHex()),
            [],
            Defaults::CURRENCY,
            $languageIdChain,
        ));
    }

    /**
     * @param non-empty-list<string> $languageCodeChain
     */
    private function buildPageCategory(array $languageCodeChain): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($this->ids->create('category'));
        $category->setCmsPageId($this->ids->create('cms-page'));
        $category->setType(CategoryDefinition::TYPE_PAGE);
        $category->addTranslated('slotConfig', [
            'content' => [
                'field' => [
                    'value' => 'en config',
                ],
            ],
        ]);

        $categoryTranslations = array_map(static function (string $languageCode) {
            $translation = new CategoryTranslationEntity();
            $translation->setUniqueIdentifier('category-translation-' . $languageCode);
            $translation->setLanguageId(self::LANGUAGE_IDS[$languageCode]);
            $translation->setSlotConfig([
                'content' => [
                    'field' => [
                        'value' => $languageCode . ' config',
                    ],
                ],
            ]);

            return $translation;
        }, $languageCodeChain);

        $category->setTranslations(new CategoryTranslationCollection($categoryTranslations));

        return $category;
    }

    private function buildContentlessCategoryRepositoryMock(CategoryEntity $category, SalesChannelContext $salesChannelContext, Request $request): CategoryRouteResponse
    {
        $categoryRepositoryMock = $this->createMock(SalesChannelRepository::class);
        $categoryRepositoryMock
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                1,
                new CategoryCollection([$category]),
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            ));

        $categoryRoute = new CategoryRoute(
            $categoryRepositoryMock,
            static::createStub(SalesChannelCmsPageLoaderInterface::class),
            new EntityCmsSlotConfigInheritanceBuilder(
                $this->createConnectionWithParentLanguageIds(['en']),
            ),
            new CategoryDefinition(),
            static::createStub(CacheTagCollector::class),
        );

        return $categoryRoute->load(
            $this->ids->get('category'),
            $request,
            $salesChannelContext,
        );
    }

    /**
     * @param non-empty-list<string> $languageCodeChain
     */
    private function createConnectionWithParentLanguageIds(array $languageCodeChain): Connection
    {
        $connection = static::createStub(Connection::class);
        $queryBuilder = static::createStub(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $parentLanguageIds = [];
        for ($i = \count($languageCodeChain) - 1; $i > 0; --$i) {
            $parentLanguageIds[] = self::LANGUAGE_IDS[$languageCodeChain[$i - 1]];
        }
        $parentLanguageIds[] = null;

        $results = array_map(function (?string $parentLanguageId): Result {
            $result = $this->createStub(Result::class);
            $result->method('fetchOne')->willReturn($parentLanguageId);

            return $result;
        }, $parentLanguageIds);

        $queryBuilder->method('executeQuery')->willReturnOnConsecutiveCalls(...$results);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
