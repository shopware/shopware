<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Cases\Storefront;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Tests\Bench\BenchCase;
use Shopware\Tests\Bench\Fixtures;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductDetailRouteBench extends BenchCase
{
    private const SUBJECT_CUSTOMER = 'customer-0';
    private const PRODUCT_KEY = 'product-10';

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            SalesChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getCustomerId()) {
            throw new \Exception('Customer not logged in for bench tests which require it!');
        }

        $this->getContainer()->get(Connection::class)->beginTransaction();
    }

    #[Bench\BeforeMethods(['setup'])]
    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 30ms +/- 5ms')]
    public function bench_load_product_detail_route_with_logged_out_user(): void
    {
        $this->getContainer()->get(ProductDetailRoute::class)
            ->load($this->ids->get(self::PRODUCT_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 30ms +/- 5ms')]
    public function bench_load_product_detail_route_with_logged_in_user(): void
    {
        $this->getContainer()->get(ProductDetailRoute::class)
            ->load($this->ids->get(self::PRODUCT_KEY), new Request(), $this->context, new Criteria());
    }
}
