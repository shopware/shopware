<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class ProductDetailReadinessCheck extends BaseCheck
{
    private const MESSAGE_SUCCESS = 'Product detail pages are OK for provided sales channels.';

    private const MESSAGE_FAILURE = 'Some or all product detail pages are unhealthy.';

    /**
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelDomainUtil $util,
        private readonly AbstractSalesChannelDomainProvider $domainProvider,
        private readonly SalesChannelRepository $productRepository,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function run(): Result
    {
        return $this->util->runAsSalesChannelRequest(
            fn () => $this->util->runWhileTrustingAllHosts(
                fn () => $this->doRun()
            )
        );
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function name(): string
    {
        return 'ProductDetailReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->domainProvider->fetchSalesChannelDomains();

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $salesChannelId => $domain) {
            $productId = $this->fetchVisibleProductId($salesChannelId);

            if ($productId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, ProductPageSeoUrlRoute::ROUTE_NAME, [
                'productId' => $productId,
            ]);

            $request = Request::create($url);
            $result = $this->util->handleRequest($request);

            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No sales channels with product detail pages found.');
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result(
            $this->name(),
            $finalStatus,
            $finalStatus === Status::OK ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILURE,
            $finalStatus === Status::OK,
            $extra
        );
    }

    /**
     * @description The product is resolved through the sales channel repository on purpose, so the same criteria
     * processing the storefront applies is used: SalesChannelProductDefinition::processCriteria() adds the
     * ProductAvailableFilter that ProductDetailRoute uses, and extensions restricting product visibility by rules
     * (for example via the sales_channel.product.process.criteria event) are taken into account as well.
     * Otherwise the check could pick a product the storefront refuses to render for an anonymous visitor,
     * which would report an intentional restriction as an unhealthy product detail page.
     */
    private function fetchVisibleProductId(string $salesChannelId): ?string
    {
        $context = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        $criteria = new Criteria();
        $criteria->setTitle('product-detail-readiness-check');
        // product.available covers the closeout handling of ProductDetailRoute::addCloseoutFilter()
        $criteria->addFilter(new EqualsFilter('available', true));
        // pick the same product on every run, so the check result is reproducible
        $criteria->addSorting(new FieldSorting('id'));
        $criteria->setLimit(1);

        return $this->productRepository->searchIds($criteria, $context)->firstId();
    }
}
