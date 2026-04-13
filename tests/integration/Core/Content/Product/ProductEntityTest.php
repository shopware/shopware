<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
class ProductEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('productProvider')]
    public function testStringifyProduct(ProductEntity $entity, string $expected): void
    {
        static::assertSame($expected, $this->renderTestTemplate($entity));
    }

    /**
     * @return iterable<string, array{0: ProductEntity, 1: string}>
     */
    public static function productProvider(): iterable
    {
        $product = new ProductEntity();
        $product->setId('fooId');

        $productWithEmptyName = clone $product;
        $productWithEmptyName->setName(null);
        $productWithEmptyName->setTranslated([]);

        $productWithName = clone $product;
        $productWithName->setName('foo');
        $productWithName->setTranslated([]);

        $productWithTranslatedName = clone $product;
        $productWithTranslatedName->setName(null);
        $productWithTranslatedName->setTranslated([
            'name' => 'translated foo',
        ]);

        $productWithNameAndTranslatedName = clone $product;
        $productWithNameAndTranslatedName->setName('foo');
        $productWithNameAndTranslatedName->setTranslated([
            'name' => 'translated foo',
        ]);

        yield 'product with empty name' => [$productWithEmptyName, 'empty'];
        yield 'product with name' => [$productWithName, 'foo'];
        yield 'product with translated name' => [$productWithTranslatedName, 'translated foo'];
        yield 'product with name and translated name' => [$productWithNameAndTranslatedName, 'translated foo'];
    }

    private function renderTestTemplate(ProductEntity $entity): string
    {
        $twig = static::getContainer()->get('twig');

        $originalLoader = $twig->getLoader();
        $twig->setLoader(new ArrayLoader([
            'test.html.twig' => '{% if page.product is empty %}empty{% else %}{{ page.product }}{% endif %}',
        ]));
        $output = $twig->render('test.html.twig', ['page' => ['product' => $entity]]);
        $twig->setLoader($originalLoader);

        return $output;
    }
}
