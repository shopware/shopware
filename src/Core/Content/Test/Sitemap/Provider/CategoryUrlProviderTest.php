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
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class CategoryUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categorySalesChannelRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $seoUrlSalesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categorySalesChannelRepository = $this->getContainer()->get('sales_channel.category.repository');
        $this->seoUrlSalesChannelRepository = $this->getContainer()->get('sales_channel.seo_url.repository');

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
                        'name' => 'Sub 5',
                        'active' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
