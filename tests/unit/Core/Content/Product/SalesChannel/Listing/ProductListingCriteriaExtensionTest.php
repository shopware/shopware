<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\ProductListingCriteriaExtensionExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ProductListingCriteriaExtension::class)]
#[CoversClass(ProductListingCriteriaExtensionExample::class)]
class ProductListingCriteriaExtensionTest extends TestCase
{
    public function testProductListingCriteriaExample(): void
    {
        $example = new ProductListingCriteriaExtensionExample();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new ProductListingCriteriaExtension(
            new Criteria(),
            $this->createMock(SalesChannelContext::class),
            'categoryId'
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: $extension,
            function: function (Criteria $criteria, SalesChannelContext $context, string $categoryId): Criteria {
                $criteria->addFilter(
                    new EqualsFilter('product.categoriesRo.id', $categoryId)
                );

                return $criteria;
            }
        );

        static::assertInstanceOf(Criteria::class, $result);
        static::assertEquals([], $result->getFilters());
    }
}
