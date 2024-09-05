<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class CategoryUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        if (!$this->getContainer()->has(ProductPageSeoUrlRoute::class)) {
            static::markTestSkipped('NEXT-16799: Sitemap module has a dependency on storefront routes');
        }

        $navigationCategoryId = $this->createRootCategoryData();

        $this->salesChannelContext = $this->createStorefrontSalesChannelContext(
            Uuid::randomHex(),
            'test-category-sitemap',
            Defaults::LANGUAGE_SYSTEM,
            [],
            $navigationCategoryId
        );

        $this->createCategoryTree($navigationCategoryId);
    }

    public function testCategoryUrlObjectContainsValidContent(): void
    {
        $urlResult = $this->getCategoryUrlProvider()->getUrls($this->salesChannelContext, 5);
        [$firstUrl] = $urlResult->getUrls();

        static::assertSame('daily', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(CategoryEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));
    }

    public function testReturnedOffsetIsValid(): void
    {
        $categoryUrlProvider = $this->getCategoryUrlProvider();

        // first run
        $urlResult = $categoryUrlProvider->getUrls($this->salesChannelContext, 3);
        static::assertIsNumeric($urlResult->getNextOffset());

        // 1+n run
        $urlResult = $categoryUrlProvider->getUrls($this->salesChannelContext, 2, $urlResult->getNextOffset());
        static::assertIsNumeric($urlResult->getNextOffset());

        // last run
        $urlResult = $categoryUrlProvider->getUrls($this->salesChannelContext, 100, $urlResult->getNextOffset()); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    public function testExcludeCategoryLinkAndFolder(): void
    {
        $urlResult = $this->getCategoryUrlProvider()->getUrls($this->salesChannelContext, 10);
        $ids = array_map(fn ($url) => $url->getIdentifier(), $urlResult->getUrls());

        // link
        static::assertNotContains('0191233394c57345a56e1b4df4db81c3', $ids);

        // folder
        static::assertNotContains('0191233394c57345a56e1b4df521dca6', $ids);
    }

    private function getCategoryUrlProvider(): CategoryUrlProvider
    {
        return new CategoryUrlProvider(
            $this->getContainer()->get(ConfigHandler::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(CategoryDefinition::class),
            $this->getContainer()->get(IteratorFactory::class),
            $this->getContainer()->get(RouterInterface::class),
        );
    }

    private function createRootCategoryData(): string
    {
        $id = Uuid::randomHex();
        $categories = [
            [
                'id' => $id,
                'name' => 'Main',
            ],
        ];

        $this->getContainer()->get('category.repository')->create($categories, Context::createDefaultContext());

        return $id;
    }

    private function createCategoryTree(string $rootId): void
    {
        $this->getContainer()->get('category.repository')->upsert([
            [
                'id' => $rootId,
                'children' => [
                    [
                        'name' => 'Sub 1',
                        'active' => true,
                    ],
                    [
                        'name' => 'Sub 2',
                        'active' => true,
                    ],
                    [
                        'name' => 'Sub 3',
                        'active' => true,
                    ],
                    [
                        'name' => 'Sub 4',
                        'active' => true,
                    ],
                    [
                        'id' => '0191233394c57345a56e1b4df4db81c3',
                        'name' => 'Sub 5',
                        'active' => true,
                        'type' => CategoryDefinition::TYPE_LINK,
                    ],
                    [
                        'id' => '0191233394c57345a56e1b4df521dca6',
                        'name' => 'Sub 6',
                        'active' => true,
                        'type' => CategoryDefinition::TYPE_FOLDER,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
