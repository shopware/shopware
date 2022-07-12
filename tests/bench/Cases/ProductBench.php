<?php

namespace Shopware\Tests\Bench\Cases;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Tests\Bench\BenchCase;
use PhpBench\Attributes as Bench;

class ProductBench extends BenchCase
{
    #[Bench\Assert('mode(variant.time.avg) < 10ms')]
    public function bench_loading_a_simple_product(): void
    {
        $criteria = new Criteria(
            $this->ids->getList(['simple-product'])
        );

        $this->getContainer()->get('product.repository')
            ->search($criteria, Context::createDefaultContext());
    }
}
