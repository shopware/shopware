<?php

namespace Shopware\Tests\Bench\Cases;

use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Tests\Bench\BenchCase;
use PhpBench\Attributes as Bench;
use Symfony\Component\HttpFoundation\Request;

class CategoryBench extends BenchCase
{
    #[Bench\Assert('mode(variant.time.avg) < 10ms')]
    public function bench_load_navigation(): void
    {
        $route = $this->getContainer()->get(NavigationRoute::class);

        $route->load(
            $this->ids->get('navigation'),
            $this->ids->get('navigation'),
            new Request(),
            $this->context,
            new Criteria()
        );
    }

}
