<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CategoryUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categorySalesChannelRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        $sc = $this->createSalesChannel([
            'navigationCategoryId' => $this->createTestData(),
        ]);
        $this->categorySalesChannelRepository = $this->getContainer()->get('sales_channel.category.repository');

        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create('', $sc['id']);
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
        static::assertSame(3, $urlResult->getNextOffset());

        // 1+n run
        $urlResult = $categoryUrlProvider->getUrls($this->salesChannelContext, 2, 3);
        static::assertSame(5, $urlResult->getNextOffset());

        // last run
        $urlResult = $categoryUrlProvider->getUrls($this->salesChannelContext, 100, 5); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    private function getCategoryUrlProvider(): CategoryUrlProvider
    {
        return new CategoryUrlProvider(
            $this->categorySalesChannelRepository,
            $this->getContainer()->get(ConfigHandler::class),
            $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class)
        );
    }

    private function createTestData(): string
    {
        $id = Uuid::randomHex();
        $categories = [
            [
                'id' => $id,
                'name' => 'Main',
                'children' => [
                    [
                        'name' => 'Sub 1',
                    ],
                    [
                        'name' => 'Sub 2',
                    ],
                    [
                        'name' => 'Sub 3',
                    ],
                    [
                        'name' => 'Sub 4',
                    ],
                    [
                        'name' => 'Sub 5',
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('category.repository')->create($categories, Context::createDefaultContext());

        return $id;
    }
}
