<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Storefront;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Tests\Bench\AbstractBenchCase;
use Shopware\Tests\Bench\Fixtures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal - only for performance benchmarks
 */
#[Bench\BeforeMethods(['setupWithLogin'])]
class ProductListingRouteBench extends AbstractBenchCase
{
    use BasicTestDataBehaviour;
    use SalesChannelApiTestBehaviour;

    private const SUBJECT_CUSTOMER = 'customer-0';
    private const CATEGORY_KEY = 'level-2.1';

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            SalesChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getCustomer() instanceof CustomerEntity) {
            throw new \Exception('Customer not logged in for bench tests which require it!');
        }

        $this->getContainer()->get(Connection::class)->beginTransaction();
    }

    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 35ms')]
    public function bench_load_product_listing_route_with_logged_out_user(): void
    {
        $this->getContainer()->get(ProductListingRoute::class)
            ->load($this->ids->get(self::CATEGORY_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 35ms')]
    public function bench_load_product_listing_route_no_criteria(): void
    {
        $this->getContainer()->get(ProductListingRoute::class)
            ->load($this->ids->get(self::CATEGORY_KEY), new Request(), $this->context, new Criteria());
    }

    #[Bench\Groups(['custom-pricing'])]
    #[Bench\Assert('mean(variant.time.avg) < 35ms')]
    public function bench_load_product_listing_route_only_active_and_price_below_80(): void
    {
        $criteria = (new Criteria())
            ->addFilter(new RangeFilter('price', [
                RangeFilter::GTE => 0.00,
                RangeFilter::LT => 80.00,
            ]))
            ->addFilter(new EqualsFilter('active', true));
        $this->getContainer()->get(ProductListingRoute::class)
            ->load($this->ids->get(self::CATEGORY_KEY), new Request(), $this->context, $criteria);
    }

    protected static function getKernel(): KernelInterface
    {
        return self::getContainer()->get('kernel');
    }
}
