<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

class SeoUrlGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

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

    protected function setUp(): void
    {
        parent::setUp();

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', Defaults::SALES_CHANNEL);

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
    public function testGenerateUrlCount(string $id, ?string $template, int $count, ?string $pathInfo): void
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
    public function testGenerateSeoPathInfo(string $id, ?string $template, int $count, ?string $pathInfo): void
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
}
