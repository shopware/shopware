<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class PromotionCodesLoader
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
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function loadIndividualCodes(string $promotionId): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('code');
        $qb->from('promotion_individual_code');
        $qb->where($qb->expr()->eq('promotion_id', ':id'));
        $qb->setParameter(':id', Uuid::fromHexToBytes($promotionId));

        /** @var array|bool $result */
        $result = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if ($result !== (array) $result) {
            return [];
        }

        return $result;
    }

    public function generateCodeFixed(): string
    {
        // ToDo NEXT-12515 - When code-pattern-handling will be implemented, use the new method
        return strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 8));
    }
}
