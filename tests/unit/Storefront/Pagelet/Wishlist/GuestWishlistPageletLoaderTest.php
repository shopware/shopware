<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Pagelet\Wishlist;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishListPageletProductCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GuestWishlistPageletLoader::class)]
class GuestWishlistPageletLoaderTest extends TestCase
{
    /**
     * The guest wishlist renders the same product boxes as the customer wishlist, so it has to load
     * the same categories for the reported analytics category path.
     */
    public function testCriteriaLoadsTheCategoriesTheAnalyticsPathNeeds(): void
    {
        $criteria = $this->captureCriteria();

        static::assertTrue($criteria->hasAssociation('categories'));
        static::assertSame([], $criteria->getAssociation('categories')->getAssociations());

        static::assertTrue($criteria->hasAssociation('mainCategories'));
        static::assertTrue($criteria->getAssociation('mainCategories')->hasAssociation('category'));
    }

    public function testCriteriaKeepsTheExistingAssociations(): void
    {
        $criteria = $this->captureCriteria();

        static::assertTrue($criteria->hasAssociation('manufacturer'));
        static::assertTrue($criteria->hasAssociation('options'));
    }

    private function captureCriteria(): Criteria
    {
        $criteria = null;

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) use (&$criteria) {
                if ($event instanceof GuestWishListPageletProductCriteriaEvent) {
                    $criteria = $event->getCriteria();
                }

                return $event;
            }
        );

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturn(false);

        $loader = new GuestWishlistPageletLoader(
            static::createStub(AbstractProductListRoute::class),
            $systemConfigService,
            $eventDispatcher,
            static::createStub(AbstractProductCloseoutFilterFactory::class)
        );

        $loader->load(new Request(), Generator::generateSalesChannelContext());

        static::assertInstanceOf(Criteria::class, $criteria);

        return $criteria;
    }
}
