<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Wishlist;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadWishlistRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Shopware\Storefront\Page\Wishlist\WishListPageProductCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(WishlistPageLoader::class)]
class WishlistPageLoaderTest extends TestCase
{
    /**
     * Analytics reports the category path of every wishlisted product, which the resolver reads from
     * the assigned categories and the sales channel main category.
     */
    public function testCriteriaLoadsTheCategoriesTheAnalyticsPathNeeds(): void
    {
        $criteria = $this->captureCriteria();

        static::assertTrue($criteria->hasAssociation('categories'));

        // the breadcrumb is a stored field on the category itself, so no ancestor association
        // is needed, which would trigger a second read
        static::assertSame([], $criteria->getAssociation('categories')->getAssociations());

        // the main category has to be preloaded, otherwise resolving it would query per product
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
                if ($event instanceof WishListPageProductCriteriaEvent) {
                    $criteria = $event->getCriteria();
                }

                return $event;
            }
        );

        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        $wishlistRoute = static::createStub(AbstractLoadWishlistRoute::class);
        $wishlistRoute->method('load')->willThrowException(new CustomerWishlistNotFoundException());

        $loader = new WishlistPageLoader($genericLoader, $wishlistRoute, $eventDispatcher);
        $loader->load(new Request(), Generator::generateSalesChannelContext(), new CustomerEntity());

        static::assertInstanceOf(Criteria::class, $criteria);

        return $criteria;
    }
}
