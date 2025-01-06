<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Cases;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes as Bench;
use PhpBench\Attributes\BeforeMethods;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Tests\Bench\AbstractBenchCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal - only for performance benchmarks
 */
class CategoryBench extends AbstractBenchCase
{
    #[BeforeMethods(['setup'])]
    #[AfterMethods(['tearDown'])]
    #[Bench\Assert('mode(variant.time.avg) < 10ms')]
    public function bench_load_navigation(): void
    {
        $route = static::getContainer()->get(NavigationRoute::class);

        $route->load(
            $this->ids->get('navigation'),
            $this->ids->get('navigation'),
            new Request(),
            $this->context,
            new Criteria()
        );
    }
}
