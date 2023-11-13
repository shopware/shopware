<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
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
     * @param list<string> $reviewIds
     *
     * @deprecated tag:v6.6.0 - second parameter `$isdDeleted` will be removed as it is not used anymore
     */
    public function updateReviewCount(array $reviewIds, bool $isDelete = false): void
    {
        if (\func_num_args() > 1) {
            Feature::triggerDeprecationOrThrow(
                'v6.6.0.0',
                'The second parameter `$isDeleted` in `ProductReviewCountService::updateReviewCount()` is not used anymore and will be removed in v6.6.0.0.',
            );
        }

        /** @var list<string> $affectedCustomers */
        $affectedCustomers = array_filter($this->connection->fetchFirstColumn(
            'SELECT DISTINCT(`customer_id`) FROM product_review WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($reviewIds)],
            ['ids' => ArrayParameterType::BINARY]
        ));

        foreach ($affectedCustomers as $customerId) {
            $this->updateReviewCountForCustomer($customerId);
        }
    }

    public function updateReviewCountForCustomer(string $customerId): void
    {
        $this->connection->executeStatement(
            'UPDATE `customer` SET review_count = (
                  SELECT COUNT(*) FROM `product_review` WHERE `customer_id` = :id AND `status` = 1
            ) WHERE id = :id',
            ['id' => $customerId]
        );
    }
}
