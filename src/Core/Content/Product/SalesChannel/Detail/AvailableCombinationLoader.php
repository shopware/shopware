<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class AvailableCombinationLoader extends AbstractAvailableCombinationLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractStockStorage $stockStorage
    ) {
    }

    public function getDecorated(): AbstractAvailableCombinationLoader
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.6.0 - Method will be removed. Use `loadCombinations` instead.
     */
    public function load(string $productId, Context $context, string $salesChannelId): AvailableCombinationResult
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'loadCombinations')
        );

        $combinations = $this->getCombinations($productId, $context, $salesChannelId);

        $result = new AvailableCombinationResult();

        foreach ($combinations as $combination) {
            try {
                $options = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $available = (bool) $combination['available'];
            $result->addCombination($options, $available);
        }

        return $result;
    }

    public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
    {
        $combinations = $this->getCombinations(
            $productId,
            $salesChannelContext->getContext(),
            $salesChannelContext->getSalesChannel()->getId()
        );

        $stocks = $this->stockStorage->load(
            new StockLoadRequest(array_keys($combinations)),
            $salesChannelContext
        );

        $result = new AvailableCombinationResult();
        foreach ($combinations as $id => $combination) {
            try {
                $options = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $available = (bool) $combination['available'];
            $stockData = $stocks->getStockForProductId($id);

            if ($stockData !== null) {
                $available = $stockData->available;
            }

            $result->addCombination($options, $available);
        }

        return $result;
    }

    /**
     * @return array<string, array{options: string, available: int, productNumber: string}>
     */
    private function getCombinations(string $productId, Context $context, string $salesChannelId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->from('product');
        $query->leftJoin('product', 'product', 'parent', 'product.parent_id = parent.id');

        $query->andWhere('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->andWhere('IFNULL(product.active, parent.active) = :active');
        $query->andWhere('product.option_ids IS NOT NULL');

        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('active', true);

        $query->innerJoin('product', 'product_visibility', 'visibilities', 'product.visibilities = visibilities.product_id');
        $query->andWhere('visibilities.sales_channel_id = :salesChannelId');
        $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));

        $query->select([
            'LOWER(HEX(product.id))',
            'product.option_ids as options',
            'product.product_number as productNumber',
            'product.available',
        ]);

        $combinations = $query->executeQuery()->fetchAllAssociative();

        return FetchModeHelper::groupUnique($combinations);
    }
}
