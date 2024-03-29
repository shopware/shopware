<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SeoUrlGenerator::class)]
class SeoUrlGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private SeoUrlGenerator $seoUrlGenerator;

    private SeoUrlRouteRegistry $seoUrlRouteRegistry;

    private TestDataCollection $ids;

    private string $deLanguageId;

    private string $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new TestDataCollection();
        $this->deLanguageId = $this->getDeDeLanguageId();

        $this->createBreadcrumbData();
        $salesChannel = $this->createSalesChannel([
            'navigationCategoryId' => $this->ids->get('rootCategory'),
        ]);

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', $salesChannel['id']);
        $this->salesChannelId = $salesChannel['id'];

        $this->seoUrlGenerator = new SeoUrlGenerator(
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get('router.default'),
            $this->getContainer()->get('request_stack'),
            $this->getContainer()->get('shopware.seo_url.twig'),
            $this->getContainer()->get(TwigVariableParserFactory::class),
            new NullLogger(),
        );

        $this->seoUrlRouteRegistry = $this->getContainer()->get(SeoUrlRouteRegistry::class);
    }

    /**
     * Checks whether the amount of generated URLs is correct. Empty SEO-URL
     * templates should lead to no SEO-URL being generated.
     */
    #[DataProvider('templateDataProvider')]
    public function testGenerateUrlCount(string $template, int $count, string $pathInfo): void
    {
        $id = $this->getValidCategoryId();

        $route = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        /** @var \Traversable<SeoUrlEntity> $urls */
        $urls = $this->seoUrlGenerator->generate(
            [$id],
            $template,
            $route,
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        static::assertIsIterable($urls);
        static::assertCount($count, iterator_to_array($urls, false));
    }

    /**
     * Checks whether the SEO-URL path generated fits the expected template.
     */
    #[DataProvider('templateDataProvider')]
    public function testGenerateSeoPathInfo(string $template, int $count, string $pathInfo): void
    {
        $id = $this->getValidCategoryId();

        if ($pathInfo === 'id') {
            $pathInfo = $id;
        }

        $route = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        /** @var SeoUrlEntity[] $urls */
        $urls = $this->seoUrlGenerator->generate(
            [$id],
            $template,
            $route,
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        static::assertIsIterable($urls);

        foreach ($urls as $url) {
            if (!empty($pathInfo)) {
                static::assertStringEndsWith($pathInfo, $url->getSeoPathInfo());
            }
        }
    }

    /**
     * @return list<array{template: string, count: int, pathInfo: string}>
     */
    public static function templateDataProvider(): array
    {
        return [
            [
                'template' => '{{ id }}',
                'count' => 1,
                'pathInfo' => 'id',
            ],
            [
                'template' => 'STATIC',
                'count' => 1,
                'pathInfo' => 'STATIC',
            ],
            [
                'template' => '',
                'count' => 0,
                'pathInfo' => '',
            ],
        ];
    }

    public function testVariantInheritance(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'route_name' => TestProductSeoUrlRoute::ROUTE_NAME,
            'entity_name' => ProductDefinition::ENTITY_NAME,
            'template' => '{{ product.translated.name }}/{{ product.productNumber }}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'parent'))
            ->price(100)
            ->visibility($this->salesChannelId)
            ->variant(
                (new ProductBuilder($ids, 'red'))
                    ->tax(null)
                    ->name('red')
                    ->build()
            )
            ->variant(
                (new ProductBuilder($ids, 'green'))
                    ->name(null)
                    ->build()
            );

        $this->getContainer()->get('product.repository')
            ->create([$product->build()], Context::createDefaultContext());

        $this->getContainer()->get(SeoUrlUpdater::class)->update(TestProductSeoUrlRoute::ROUTE_NAME, array_values($ids->getList(['parent', 'red', 'green'])));

        $urls = $connection
            ->fetchAllAssociative(
                'SELECT LOWER(HEX(foreign_key)) as foreign_key, seo_path_info FROM seo_url WHERE route_name = :route AND foreign_key IN (:ids) AND sales_channel_id = :channel',
                [
                    'route' => TestProductSeoUrlRoute::ROUTE_NAME,
                    'ids' => Uuid::fromHexToBytesList($ids->getList(['parent', 'red', 'green'])),
                    'channel' => Uuid::fromHexToBytes($this->salesChannelId),
                ],
                ['ids' => ArrayParameterType::BINARY]
            );

        $urls = FetchModeHelper::keyPair($urls);

        static::assertCount(3, $urls);
        static::assertArrayHasKey($ids->get('parent'), $urls);
        static::assertArrayHasKey($ids->get('green'), $urls);
        static::assertArrayHasKey($ids->get('red'), $urls);

        // name = parent | number = parent
        static::assertEquals('parent/parent', $urls[$ids->get('parent')]);

        // name = red | number = red
        static::assertEquals('red/red', $urls[$ids->get('red')]);

        // name = parent | number = green
        static::assertEquals('parent/green', $urls[$ids->get('green')]);
    }

    public function testTemplateWithMultipleVariantOptions(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'parent'))
            ->price(100)
            ->visibility($this->salesChannelId)
            ->variant(
                (new ProductBuilder($ids, 'redProduct'))
                    ->name('tshirt')
                    ->option('red', 'color')
                    ->build()
            )
            ->variant(
                (new ProductBuilder($ids, 'greenProduct'))
                    ->name('tshirt')
                    ->option('green', 'color', 1)
                    ->option('xl', 'size', 2)
                    ->build()
            );

        $this->getContainer()->get('product.repository')
            ->create([$product->build()], Context::createDefaultContext());

        $productIds = $ids->getList(['parent', 'redProduct', 'greenProduct']);
        $template = '{{ product.translated.name|lower }}{% if product.options %}{% for var in product.options|sort((a,b)=> a.position <=> b.position) %}-{{ var.name }}{% endfor %}{% endif %}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestProductSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($productIds, $template, $route, Context::createDefaultContext(), $this->salesChannelContext->getSalesChannel());

        $expected = ['parent', 'tshirt-red', 'tshirt-green-xl'];
        foreach ($result as $index => $seoUrl) {
            static::assertEquals($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testTemplateWithMultipleAssociations(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'product'))
            ->price(100)
            ->visibility($this->salesChannelId)
            ->manufacturer('shopware')
            ->category('test category');

        $this->getContainer()->get('product.repository')
            ->create([$product->build()], Context::createDefaultContext());

        $productIds = $ids->getList(['product']);
        $template = '{% if product.categories %}{% for var in product.categories %}{{ var.translated.name }}-{% endfor %}{% endif %}{{ product.manufacturer.translated.name }}-{{ product.translated.name|lower }}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestProductSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($productIds, $template, $route, Context::createDefaultContext(), $this->salesChannelContext->getSalesChannel());

        $expected = ['test-category-shopware-product'];
        foreach ($result as $index => $seoUrl) {
            static::assertEquals($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testTemplateWithCustomTwigExtension(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'my product'))
            ->price(100)
            ->visibility($this->salesChannelId);

        $this->getContainer()->get('product.repository')
            ->create([$product->build()], Context::createDefaultContext());

        $productIds = $ids->getList(['product']);
        $template = '{{ product.translated.name|lastBigLetter }}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestProductSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($productIds, $template, $route, Context::createDefaultContext(), $this->salesChannelContext->getSalesChannel());

        $expected = ['my-producT'];
        foreach ($result as $index => $seoUrl) {
            static::assertEquals($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testNotBeingStateful(): void
    {
        $categoryIds = $this->getCategoryIds(2);

        static::assertCount(2, $categoryIds, 'this is important for the test as you need more items to iterate for a context switch test');

        /** @var SeoUrlRouteInterface $seoRoute */
        $seoRoute = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);

        /** @var \Generator<SeoUrlEntity> $firstRun */
        $firstRun = $this->seoUrlGenerator->generate(
            $categoryIds,
            'template first run',
            $seoRoute,
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );
        /** @var \Generator<SeoUrlEntity> $secondRun */
        $secondRun = $this->seoUrlGenerator->generate(
            $categoryIds,
            'template second run',
            $seoRoute,
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        /** @var SeoUrlEntity $url */
        foreach ($firstRun as $url) {
            static::assertSame('template first run', $url->getSeoPathInfo());

            break;
        }

        // this changes the template of the twig state to second template
        foreach ($secondRun as $_) {
            break;
        }

        /** @var SeoUrlEntity $url */
        foreach ($firstRun as $url) {
            static::assertSame('template first run', $url->getSeoPathInfo());
        }
    }

    public function testErrorLogging(): void
    {
        $logger = new class() extends AbstractLogger {
            /**
             * @var mixed[]
             */
            public array $logs = [];

            /**
             * @param int|string $level
             * @param mixed[] $context
             */
            public function log(mixed $level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[$level][$message][] = $context;
            }
        };
        $seoUrlGenerator = new SeoUrlGenerator(
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get('router.default'),
            $this->getContainer()->get('request_stack'),
            $this->getContainer()->get('shopware.seo_url.twig'),
            $this->getContainer()->get(TwigVariableParserFactory::class),
            $logger,
        );

        /** @var SeoUrlRouteInterface $seoRoute */
        $seoRoute = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);

        $urls = $seoUrlGenerator->generate(
            [$this->getValidCategoryId()],
            // broken twig template
            '{% for part in category.seoBreadcrumb %}{{ part }}/',
            $seoRoute,
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        // generator needs to be triggered to fail
        foreach ($urls as $_) {
            break;
        }

        static::assertNotSame([], $logger->logs);
        $logger->logs = [];

        /** @var \Generator<SeoUrlEntity> $urls */
        $urls = $seoUrlGenerator->generate(
            [$this->getValidCategoryId()],
            // invalid twig context
            '{{ product.id }}',
            $seoRoute,
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        // generator needs to be triggered to fail
        foreach ($urls as $_) {
            break;
        }

        static::assertNotSame([], $logger->logs);
    }

    private function createBreadcrumbData(): void
    {
        $this->getContainer()->get('category.repository')->create([
            [
                'id' => $this->ids->create('rootCategory'),
                'translations' => [
                    ['name' => 'EN-Entry', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE-Entry', 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => Uuid::randomHex(),
                        'translations' => [
                            ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create('childCategory'),
                                'translations' => [
                                    ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return list<string>|list<array<string, string>>
     */
    private function getCategoryIds(int $count): array
    {
        /** @var EntityRepository<CategoryCollection> $repository */
        $repository = $this->getContainer()->get('category.repository');

        $criteria = (new Criteria())->setLimit($count);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }
}
