<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('business-ops')]
/**
 * @final
 */
class ProductReviewCountService
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param string[] $reviewIds
     */
    public function updateReviewCount(array $reviewIds, bool $isDelete = false): void
    {
        $reviewIds = \array_map(fn ($id) => Uuid::fromHexToBytes($id), $reviewIds);

        $results = $this->connection->executeQuery(
            'SELECT * FROM product_review WHERE id IN (:ids)',
            ['ids' => $reviewIds],
            ['ids' => ArrayParameterType::STRING]
        )->fetchAllAssociative();

        foreach ($results as $result) {
            if (!isset($result['customer_id'])) {
                continue;
            }

            $update = new RetryableQuery(
                $this->connection,
                $this->connection->prepare(sprintf(
                    'UPDATE `customer` SET review_count = review_count %s 1 WHERE id = :id',
                    $isDelete ? '-' : '+'
                ))
            );

            $update->execute([
                'id' => $result['customer_id'],
            ]);
        }
    }
}
