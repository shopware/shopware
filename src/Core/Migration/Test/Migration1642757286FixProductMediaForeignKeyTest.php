<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class Migration1642757286FixProductMediaForeignKeyTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testProductMediaConstraint(): void
    {
        $ids = new IdsCollection();

        $productRepository = $this->getContainer()->get('product.repository');

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->media('m1')
            ->cover('m1');

        $productRepository->create([$product->build()], $ids->getContext());

        $productMediaRepository = $this->getContainer()->get('product_media.repository');
        $productMediaRepository->delete([['id' => $ids->get('m1')]], $ids->getContext());

        $product = $productRepository->search(new Criteria([$ids->get('p1')]), $ids->getContext())->first();
        static::assertNull($product->getCoverId());
    }
}
