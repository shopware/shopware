<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Uuid\Uuid;

class AvailableCombinationLoader
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(string $productId, Context $context): AvailableCombinationResult
    {
        $query = $this->connection->createQueryBuilder();
        $query->from('product');

        $query->andWhere('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->andWhere('product.active = :active');
        $query->andWhere('product.option_ids IS NOT NULL');

        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter(':active', true);

        $query->select([
            'LOWER(HEX(product.id))',
            'product.option_ids as options',
            'product.product_number as productNumber',
            'product.min_purchase as minPurchase',
            'product.stock as stock',
            'product.is_closeout as isCloseout',
        ]);

        $combinations = $query->execute()->fetchAll();
        $combinations = FetchModeHelper::groupUnique($combinations);

        $hashes = [];
        $optionIds = [];

        foreach ($combinations as $key => &$combination) {
            $combination['options'] = json_decode($combination['options'], true);

            if (!$combination['isCloseout']) {
                continue;
            }

            $stock = (int) $combination['stock'];

            $minPurchase = (int) $combination['minPurchase'];

            if ($stock < $minPurchase) {
                unset($combinations[$key]);
            }
        }

        foreach ($combinations as $combination) {
            sort($combination['options']);
            $hash = md5(json_encode($combination['options']));

            $hashes[$hash] = true;
            foreach ($combination['options'] as $optionId) {
                $optionIds[$optionId] = true;
            }
        }

        return new AvailableCombinationResult($hashes, $optionIds);
    }
}
