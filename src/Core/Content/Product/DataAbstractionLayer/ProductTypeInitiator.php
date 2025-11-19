<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductTypeBeforeChangeEvent;
use Shopware\Core\Content\Product\Events\ProductTypeChangedEvent;
use Shopware\Core\Content\Product\ProductTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ProductTypeInitiator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProductTypeRegistry $productTypeRegistry,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param string[] $ids
     */
    public function update(array $ids, Context $context): void
    {
        $remaining = array_values(array_unique($ids));

        foreach ($this->productTypeRegistry->getTypeHandlers() as $type) {
            if ($remaining === []) {
                break;
            }

            $matched = $type->getMatchedIds($remaining, $context);

            if ($matched === []) {
                continue;
            }

            $this->updateTypeForProducts($matched, $type->getType(), $context);

            $remaining = array_values(array_diff($remaining, $matched));
        }
    }

    /**
     * @param list<string> $ids
     */
    private function updateTypeForProducts(array $ids, string $type, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $idsToUpdate = $this->getIdsWithoutAssignedType($ids, $context);

        if ($idsToUpdate === []) {
            return;
        }

        $this->eventDispatcher->dispatch(new ProductTypeBeforeChangeEvent($idsToUpdate, $type, $context));

        $updatedRows = $this->connection->executeStatement(
            'UPDATE `product`
             SET `type` = :type
             WHERE `id` IN (:ids)
               AND `version_id` = :version AND type IS NULL',
            [
                'type' => $type,
                'ids' => Uuid::fromHexToBytesList($idsToUpdate),
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        if ($updatedRows <= 0) {
            return;
        }

        $this->eventDispatcher->dispatch(new ProductTypeChangedEvent($idsToUpdate, $type, $context));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getIdsWithoutAssignedType(array $ids, Context $context): array
    {
        /** @var list<string> $idsWithoutType */
        $idsWithoutType = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`))
             FROM `product`
             WHERE `id` IN (:ids)
               AND `version_id` = :version
               AND `type` IS NULL',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        return array_values($idsWithoutType);
    }
}
