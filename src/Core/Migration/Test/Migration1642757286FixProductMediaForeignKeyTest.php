<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
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

        $productRepository->create([$product->build()], Context::createDefaultContext());

        $productMediaRepository = $this->getContainer()->get('product_media.repository');
        $productMediaRepository->delete([['id' => $ids->get('m1')]], Context::createDefaultContext());

        $product = $productRepository->search(new Criteria([$ids->get('p1')]), Context::createDefaultContext())->first();
        static::assertNull($product->getCoverId());
    }
}
