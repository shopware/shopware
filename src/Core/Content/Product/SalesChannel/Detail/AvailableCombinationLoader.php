<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @package inventory
 */
class AvailableCombinationLoader
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @deprecated tag:v6.5.0
     * Parameter $salesChannelId will be mandatory in future implementation
     */
    public function load(string $productId, Context $context/*, string $salesChannelId*/): AvailableCombinationResult
    {
        $salesChannelId = null;
        if (\func_num_args() === 3) {
            $salesChannelId = func_get_arg(2);

            if (\gettype($salesChannelId) !== 'string') {
                throw new \InvalidArgumentException('Argument 3 $salesChannelId must be of type string.');
            }
        }

        if ($salesChannelId === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                \sprintf('"%s::%s()" will require the salesChannelId as third parameter in v6.5.0.0', __CLASS__, __METHOD__)
            );
        }

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

        if ($salesChannelId !== null) {
            $query->innerJoin('product', 'product_visibility', 'visibilities', 'product.visibilities = visibilities.product_id');
            $query->andWhere('visibilities.sales_channel_id = :salesChannelId');
            $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));
        }

        $query->select([
            'LOWER(HEX(product.id))',
            'product.option_ids as options',
            'product.product_number as productNumber',
            'product.available',
        ]);

        $combinations = $query->executeQuery()->fetchAllAssociative();
        $combinations = FetchModeHelper::groupUnique($combinations);

        $result = new AvailableCombinationResult();

        foreach ($combinations as $combination) {
            $available = (bool) $combination['available'];

            $options = json_decode($combination['options'], true);
            if ($options === false) {
                continue;
            }

            $result->addCombination($options, $available);
        }

        return $result;
    }
}
