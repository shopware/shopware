<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductEntity::class)]
class ProductEntityTest extends TestCase
{
    public function testStringify(): void
    {
        $entity = new ProductEntity();
        $entity->setId('fooId');

        static::assertSame('', (string) $entity);

        $entity->setName('foo');

        static::assertSame('foo', (string) $entity);

        $entity->setTranslated([
            'name' => 'translated foo',
        ]);

        static::assertSame('translated foo', (string) $entity);
    }

    public function testDeliveryDateSpansTomorrowToTheDayAfter(): void
    {
        $deliveryDate = (new ProductEntity())->getDeliveryDate();

        static::assertTrue($deliveryDate->getEarliest() < $deliveryDate->getLatest());
    }

    public function testRestockDeliveryDateShiftsByTheRestockTime(): void
    {
        $product = new ProductEntity();
        $product->setRestockTime(3);

        $deliveryDate = $product->getDeliveryDate();
        $restockDate = $product->getRestockDeliveryDate();

        static::assertEquals($deliveryDate->getEarliest()->modify('+3 day'), $restockDate->getEarliest());
    }

    public function testIsReleasedWithoutAReleaseDate(): void
    {
        static::assertTrue((new ProductEntity())->isReleased());
    }

    public function testIsReleasedComparesTheReleaseDateWithNow(): void
    {
        $released = new ProductEntity();
        $released->setReleaseDate(new \DateTimeImmutable('-1 day'));
        static::assertTrue($released->isReleased());

        $upcoming = new ProductEntity();
        $upcoming->setReleaseDate(new \DateTimeImmutable('+1 day'));
        static::assertFalse($upcoming->isReleased());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStatesRoundTripOnTheLegacyPath(): void
    {
        $entity = new ProductEntity();
        $entity->setStates(['is-physical']);

        static::assertSame(['is-physical'], $entity->getStates());
    }

    public function testGetStatesThrowsWhenFeatureActive(): void
    {
        $this->expectException(FeatureException::class);
        (new ProductEntity())->getStates();
    }

    public function testSetStatesThrowsWhenFeatureActive(): void
    {
        $this->expectException(FeatureException::class);
        (new ProductEntity())->setStates(['is-physical']);
    }
}
