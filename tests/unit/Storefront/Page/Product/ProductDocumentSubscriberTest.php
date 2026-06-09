<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\ProductDocumentSubscriber;
use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentSubscriber::class)]
class ProductDocumentSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ProductPageCriteriaEvent::class => 'addProductDocuments',
        ], ProductDocumentSubscriber::getSubscribedEvents());
    }

    public function testAddProductDocuments(): void
    {
        $criteria = new Criteria();
        $event = new ProductPageCriteriaEvent(
            'product-id',
            $criteria,
            $this->createMock(SalesChannelContext::class),
        );

        (new ProductDocumentSubscriber())->addProductDocuments($event);

        static::assertTrue($criteria->hasAssociation('productDocuments'));

        $productDocumentsCriteria = $criteria->getAssociation('productDocuments');
        static::assertTrue($productDocumentsCriteria->hasAssociation('media'));

        $sorting = $productDocumentsCriteria->getSorting();
        static::assertCount(1, $sorting);
        static::assertSame('position', $sorting[0]->getField());
    }
}
