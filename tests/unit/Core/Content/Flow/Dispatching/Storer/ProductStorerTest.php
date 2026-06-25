<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ProductStorer;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\ProductAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductStorer::class)]
class ProductStorerTest extends TestCase
{
    private ProductStorer $storer;

    private MockObject&ProductProvider $productProvider;

    protected function setUp(): void
    {
        $this->productProvider = $this->createMock(ProductProvider::class);

        $this->storer = new ProductStorer(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->productProvider
        );
    }

    public function testStoreWithAware(): void
    {
        $product = new ProductEntity();
        $product->setId('product-id');

        $event = new ReviewFormEvent(Context::createDefaultContext(), '', new MailRecipientStruct([]), new DataBag(), 'product-id', '', $product);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ProductAware::PRODUCT_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ProductAware::PRODUCT_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('product', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = new ProductEntity();
        $entity->setId('id');

        $this->productProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('product');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => 'id'], []);
        $this->storer->restore($storable);
        $this->productProvider->expects($this->once())->method('getData')->willReturn(null);

        $res = $storable->getData('product');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['productId' => null], []);
        $this->storer->restore($storable);
        $product = $storable->getData('product');

        static::assertNull($product);
    }
}
