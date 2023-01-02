<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Shopware\Core\Framework\Log\Package;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.5.0 - Use PromotionCodeService instead
 * @package checkout
 */
#[Package('checkout')]
class PromotionCodesLoader
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
     * @return list<string>
     */
    public function loadIndividualCodes(string $promotionId): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', PromotionCodeService::class)
        );

        $qb = $this->connection->createQueryBuilder();

        $qb->select('code');
        $qb->from('promotion_individual_code');
        $qb->where($qb->expr()->eq('promotion_id', ':id'));
        $qb->setParameter('id', Uuid::fromHexToBytes($promotionId));

        /** @var list<string>|bool $result */
        $result = $qb->executeQuery()->fetchFirstColumn();

        if ($result !== (array) $result) {
            return [];
        }

        return $result;
    }
}
