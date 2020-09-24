<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
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

    /**
     * @deprecated tag:v6.4.0 - Return type hint will be added with v6.4.0
     *
     * @return AvailableCombinationResult
     */
    public function load(string $productId, Context $context)/*: AvailableCombinationResult*/
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

        $query->select([
            'LOWER(HEX(product.id))',
            'product.option_ids as options',
            'product.product_number as productNumber',
            'product.available',
        ]);

        $combinations = $query->execute()->fetchAll();
        $combinations = FetchModeHelper::groupUnique($combinations);

        $available = [];

        foreach ($combinations as $combination) {
            $combination['options'] = json_decode($combination['options'], true);

            $available[] = $combination;
        }

        $result = new AvailableCombinationResult();

        foreach ($available as $combination) {
            $result->addCombination($combination['options']);
        }

        return $result;
    }
}
