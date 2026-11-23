<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Twig\Loader\ArrayLoader;

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

    #[DataProvider('twigOutputProvider')]
    public function testRendersInTwigAsItsDisplayName(ProductEntity $product, string $expected): void
    {
        $twig = new TwigEnvironment(new ArrayLoader([
            'test.html.twig' => '{% if page.product is empty %}empty{% else %}{{ page.product }}{% endif %}',
        ]));

        static::assertSame($expected, $twig->render('test.html.twig', ['page' => ['product' => $product]]));
    }

    public static function twigOutputProvider(): \Generator
    {
        yield 'a product without any name counts as empty' => [self::product(name: null, translated: []), 'empty'];
        yield 'a product prints its name' => [self::product(name: 'foo', translated: []), 'foo'];
        yield 'a product prints its translated name' => [self::product(name: null, translated: ['name' => 'translated foo']), 'translated foo'];
        yield 'the translated name wins over the name' => [self::product(name: 'foo', translated: ['name' => 'translated foo']), 'translated foo'];
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

    public function testIsGuaranteeConfirmedTreatsAnInheritedValueAsUnconfirmed(): void
    {
        $product = new ProductEntity();

        static::assertNull($product->get('guaranteeConfirmed'));
        static::assertFalse($product->isGuaranteeConfirmed());

        $product->setGuaranteeConfirmed(true);

        static::assertTrue($product->isGuaranteeConfirmed());
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

    /**
     * @param array<string, string> $translated
     */
    private static function product(?string $name, array $translated): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId('fooId');
        $product->setName($name);
        $product->setTranslated($translated);

        return $product;
    }
}
