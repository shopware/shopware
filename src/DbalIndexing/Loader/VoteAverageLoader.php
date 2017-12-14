<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Loader;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\ProductVoteAverage\Struct\ProductVoteAverageBasicCollection;
use Shopware\Api\ProductVoteAverage\Struct\ProductVoteAverageBasicStruct;

class VoteAverageLoader
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(array $productUuids): ProductVoteAverageBasicCollection
    {
        $query = $this->connection->createQueryBuilder();
        $query->addSelect([
            "CONCAT(product_vote.product_uuid, '-', product_vote.shop_uuid)",
            'product_vote.product_uuid',
            'product_vote.shop_uuid',
            'COUNT(uuid) as pointCount',
            'points as point',
        ]);

        $query->from('product_vote');
        $query->andWhere('product_vote.product_uuid IN (:uuids)');
        $query->andWhere('product_vote.active = 1');
        $query->setParameter(':uuids', $productUuids, Connection::PARAM_STR_ARRAY);

        $query->addGroupBy('product_vote.product_uuid');
        $query->addGroupBy('product_vote.shop_uuid');
        $query->addGroupBy('product_vote.points');

        $rows = $query->execute()->fetchAll(\PDO::FETCH_GROUP);

        $collection = new ProductVoteAverageBasicCollection();
        foreach ($rows as $productUuid => $votes) {
            $total = 0;
            $count = 0;

            $vote = new ProductVoteAverageBasicStruct();
            $first = $votes[0];

            $vote->setOnePointCount(0);
            $vote->setTwoPointCount(0);
            $vote->setThreePointCount(0);
            $vote->setFourPointCount(0);
            $vote->setFivePointCount(0);
            $vote->setProductUuid($first['product_uuid']);
            $vote->setShopUuid($first['shop_uuid']);

            foreach ($votes as $voteCount) {
                $point = $voteCount['point'];
                $total += (int) ($point * $voteCount['pointCount']);
                $count += (int) $voteCount['pointCount'];

                switch ($point) {
                    case 1:
                        $vote->setOnePointCount((int) $voteCount['pointCount']);
                        break;
                    case 2:
                        $vote->setTwoPointCount((int) $voteCount['pointCount']);
                        break;
                    case 3:
                        $vote->setThreePointCount((int) $voteCount['pointCount']);
                        break;
                    case 4:
                        $vote->setFourPointCount((int) $voteCount['pointCount']);
                        break;
                    case 5:
                        $vote->setFivePointCount((int) $voteCount['pointCount']);
                        break;
                }
            }

            $vote->setUuid(Uuid::uuid4()->toString());
            $vote->setAverage($total / $count);
            $vote->setTotal($count);
            $collection->add($vote);
        }

        return $collection;
    }
}
