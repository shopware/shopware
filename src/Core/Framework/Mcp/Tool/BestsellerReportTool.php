<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-bestseller-report', description: 'Get the top-selling products by quantity in a date range. Excludes cancelled orders. Returns product name, number, total quantity sold, order count, and total revenue. Provide ISO 8601 dates (e.g. "2025-01-01"). Returns {success, data: {period, bestsellers: [{productId, productNumber, name, totalQuantity, orderCount, totalRevenue}]}}.')]
#[Package('framework')]
class BestsellerReportTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $from, string $to, int $limit = 10, string $salesChannelId = ''): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'order:read')) {
            return $error;
        }

        if ($limit < 1 || $limit > 100) {
            return $this->error('Limit must be between 1 and 100.');
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $criteria->addFilter(new EqualsFilter('type', 'product'));

        $criteria->addFilter(new RangeFilter('order.orderDateTime', [
            RangeFilter::GTE => $from,
            RangeFilter::LTE => $to,
        ]));

        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('order.stateMachineState.technicalName', 'cancelled'),
        ]));

        if ($salesChannelId !== '') {
            $criteria->addFilter(new EqualsFilter('order.salesChannelId', $salesChannelId));
        }

        $criteria->addAggregation(
            new TermsAggregation(
                'bestsellers',
                'productId',
                $limit * 3,
                null,
                new SumAggregation('totalQuantity', 'quantity'),
            )
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'revenue',
                'productId',
                $limit * 3,
                null,
                new SumAggregation('totalRevenue', 'totalPrice'),
            )
        );

        $lineItemRepository = $this->registry->getRepository('order_line_item');
        $result = $lineItemRepository->search($criteria, $context);

        $aggregations = $result->getAggregations();
        $bestsellers = $aggregations->get('bestsellers');
        $revenueAgg = $aggregations->get('revenue');

        $revenueByProduct = [];
        if ($revenueAgg instanceof TermsResult) {
            foreach ($revenueAgg->getBuckets() as $bucket) {
                $bucketResult = $bucket->getResult();
                $revenueByProduct[$bucket->getKey() ?? ''] = $bucketResult instanceof SumResult ? $bucketResult->getSum() : 0;
            }
        }

        if (!$bestsellers instanceof TermsResult || $bestsellers->getBuckets() === []) {
            return $this->success([
                'period' => ['from' => $from, 'to' => $to],
                'bestsellers' => [],
            ]);
        }

        $ranked = [];
        foreach ($bestsellers->getBuckets() as $bucket) {
            $productId = $bucket->getKey() ?? '';
            $bucketResult = $bucket->getResult();
            $totalQuantity = $bucketResult instanceof SumResult ? (int) $bucketResult->getSum() : 0;

            $ranked[] = [
                'productId' => $productId,
                'totalQuantity' => $totalQuantity,
                'orderCount' => $bucket->getCount(),
                'totalRevenue' => $revenueByProduct[$productId] ?? 0,
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['totalQuantity'] <=> $a['totalQuantity']);
        $ranked = \array_slice($ranked, 0, $limit);

        $productIds = array_column($ranked, 'productId');
        $products = $this->loadProducts($productIds, $context);

        $bestsellersData = [];
        foreach ($ranked as $item) {
            $product = $products[$item['productId']] ?? null;

            $bestsellersData[] = [
                'productId' => $item['productId'],
                'productNumber' => $product?->getProductNumber() ?? 'unknown',
                'name' => $product?->getName() ?? 'unknown',
                'totalQuantity' => $item['totalQuantity'],
                'orderCount' => $item['orderCount'],
                'totalRevenue' => $item['totalRevenue'],
            ];
        }

        return $this->success([
            'period' => ['from' => $from, 'to' => $to],
            'bestsellers' => $bestsellersData,
        ]);
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, ProductEntity>
     */
    private function loadProducts(array $productIds, \Shopware\Core\Framework\Context $context): array
    {
        $productRepository = $this->registry->getRepository('product');
        $criteria = new Criteria($productIds);

        $result = $productRepository->search($criteria, $context);

        $map = [];
        foreach ($result->getElements() as $product) {
            \assert($product instanceof ProductEntity);
            $map[$product->getId()] = $product;
        }

        return $map;
    }
}
