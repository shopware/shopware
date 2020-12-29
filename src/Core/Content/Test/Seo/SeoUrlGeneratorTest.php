<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

class SeoUrlGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var SeoUrlGenerator
     */
    private $seoUrlGenerator;

    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var string
     */
    private $deLanguageId;

    /**
     * @var string
     */
    private $salesChannelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new TestDataCollection();
        $this->deLanguageId = $this->getDeDeLanguageId();

        $this->createBreadcrumData();
        $salesChannel = $this->createSalesChannel([
            'navigationCategoryId' => $this->ids->get('rootCategory'),
        ]);

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', $salesChannel['id']);
        $this->salesChannelId = $salesChannel['id'];

        $this->seoUrlGenerator = new SeoUrlGenerator(
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get('slugify'),
            $this->getContainer()->get('router.default'),
            $this->getContainer()->get('request_stack')
        );

        $this->seoUrlRouteRegistry = $this->getContainer()->get(SeoUrlRouteRegistry::class);
    }

    /**
     * Checks wether the amount of generated URLs is correct. Empty SEO-URL
     * templates should lead to no SEO-URL being generated.
     *
     * @dataProvider templateDataProvider
     */
    public function testGenerateUrlCount(string $id, string $template, int $count, ?string $pathInfo): void
    {
        /** @var SeoUrlEntity[] $urls */
        $urls = $this->seoUrlGenerator->generate(
            [$id],
            $template,
            $this->seoUrlRouteRegistry->findByRouteName(NavigationPageSeoUrlRoute::ROUTE_NAME),
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        static::assertIsIterable($urls);
        static::assertCount($count, $urls);
    }

    /**
     * Checks wether the SEO-URL path generated fits the expected template.
     *
     * @dataProvider templateDataProvider
     */
    public function testGenerateSeoPathInfo(string $id, string $template, int $count, ?string $pathInfo): void
    {
        /** @var SeoUrlEntity[] $urls */
        $urls = $this->seoUrlGenerator->generate(
            [$id],
            $template,
            $this->seoUrlRouteRegistry->findByRouteName(NavigationPageSeoUrlRoute::ROUTE_NAME),
            $this->salesChannelContext->getContext(),
            $this->salesChannelContext->getSalesChannel()
        );

        static::assertIsIterable($urls);

        foreach ($urls as $url) {
            static::assertStringEndsWith($pathInfo, $url->getSeoPathInfo());
        }
    }

    /**
     * @dataProvider seoUrlContentDataProvider
     */
    public function testSeoBreadcrumb(string $output, bool $useDeLanguage): void
    {
        $language = $useDeLanguage ? $this->deLanguageId : Defaults::LANGUAGE_SYSTEM;
        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$language]
        );

        /** @var SeoUrlEntity[] $urls */
        $urls = $this->seoUrlGenerator->generate(
            [$this->ids->get('childCategory')],
            NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
            $this->seoUrlRouteRegistry->findByRouteName(NavigationPageSeoUrlRoute::ROUTE_NAME),
            $context,
            $this->salesChannelContext->getSalesChannel()
        );

        static::assertIsIterable($urls);

        foreach ($urls as $url) {
            static::assertSame($output, $url->getSeoPathInfo());
        }
    }

    public function seoUrlContentDataProvider(): array
    {
        return [
            [
                'EN-A/EN-B/',
                false,
            ],
            [
                'DE-A/DE-B/',
                true,
            ],
        ];
    }

    public function templateDataProvider(): array
    {
        return [
            [
                'id' => $this->getValidCategoryId(),
                'template' => '{{ category.id }}',
                'count' => 1,
                'pathInfo' => $this->getValidCategoryId(),
            ],
            [
                'id' => $this->getValidCategoryId(),
                'template' => 'STATIC',
                'count' => 1,
                'pathInfo' => 'STATIC',
            ],
            [
                'id' => $this->getValidCategoryId(),
                'template' => '',
                'count' => 0,
                'pathInfo' => '',
            ],
        ];
    }

    public function testVariantInheritance(): void
    {
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'parent'))
            ->price(Defaults::CURRENCY, 100)
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

        $urls = $this->getContainer()
            ->get(Connection::class)
            ->fetchAll(
                'SELECT LOWER(HEX(foreign_key)) as foreign_key, seo_path_info FROM seo_url WHERE route_name = :route AND foreign_key IN (:ids) AND sales_channel_id = :channel',
                [
                    'route' => 'frontend.detail.page',
                    'ids' => Uuid::fromHexToBytesList($ids->getList(['parent', 'red', 'green'])),
                    'channel' => Uuid::fromHexToBytes($this->salesChannelId),
                ],
                ['ids' => Connection::PARAM_STR_ARRAY]
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

    private function createBreadcrumData(): void
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
        ], $this->ids->getContext());
    }
}
