<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Struct\Uuid;

class VariantCombinationLoader
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(string $productId, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(product.id))', 'product.variation_ids');
        $query->from('product');
        $query->where('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->setParameter('id', Uuid::fromHexToBytes($context->getVersionId()));
        $query->andWhere('product.variation_ids IS NOT NULL');

        $combinations = $query->execute()->fetchAll();
        $combinations = FetchModeHelper::keyPair($combinations);

        foreach ($combinations as &$combination) {
            $combination = json_decode($combination, true);
        }

        return $combinations;
    }
}
