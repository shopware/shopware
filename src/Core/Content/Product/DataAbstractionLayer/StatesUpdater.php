<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductStatesBeforeChangeEvent;
use Shopware\Core\Content\Product\Events\ProductStatesChangedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
 */
#[Package('framework')]
class StatesUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
     *
     * @param string[] $ids
     */
    public function update(array $ids, Context $context): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'update', 'v6.8.0.0')
        );

        $sql = 'SELECT LOWER(HEX(`product`.`id`)) as id,
                IF(`product_download`.`id` IS NOT NULL, 1, 0) as hasDownloads,
                `product`.`type`,
                `product`.`states`
                FROM `product`
                LEFT JOIN `product_download` ON `product`.`id` = `product_download`.`product_id`
                AND `product`.`version_id` = `product_download`.`product_version_id`
                WHERE `product`.`id` IN (:ids)
                AND `product`.`version_id` = :versionId
                GROUP BY `product`.`id`';

        $params = [
            'ids' => Uuid::fromHexToBytesList($ids),
            'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
        ];

        $products = $this->connection->fetchAllAssociative(
            $sql,
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );

        $updates = [];
        foreach ($products as $product) {
            // Only update states for physical and digital products, as the states are only relevant for these types. For other types, the states will be left unchanged.
            if (isset($product['type']) && $product['type'] !== ProductDefinition::TYPE_PHYSICAL && $product['type'] !== ProductDefinition::TYPE_DIGITAL) {
                continue;
            }

            $newStates = $this->getNewStates($product);
            $oldStates = $product['states'] ? json_decode((string) $product['states'], true, 512, \JSON_THROW_ON_ERROR) : [];

            if (array_diff($newStates, $oldStates) === []) {
                continue;
            }

            $updates[] = new UpdatedStates($product['id'], $oldStates, $newStates);
        }

        if ($updates === []) {
            return;
        }

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `product` SET `states` = :states, `type` = :type WHERE `id` = :id AND `version_id` = :version')
        );

        $event = new ProductStatesBeforeChangeEvent($updates, $context);
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getUpdatedStates() as $updatedStates) {
            $query->execute([
                'states' => json_encode($updatedStates->getNewStates(), \JSON_THROW_ON_ERROR),
                'type' => \in_array(State::IS_DOWNLOAD, $updatedStates->getNewStates(), true) ? ProductDefinition::TYPE_DIGITAL : ProductDefinition::TYPE_PHYSICAL,
                'id' => Uuid::fromHexToBytes($updatedStates->getId()),
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ]);
        }

        $this->eventDispatcher->dispatch(new ProductStatesChangedEvent($event->getUpdatedStates(), $context));
    }

    /**
     * @param mixed[] $product
     *
     * @return string[]
     */
    private function getNewStates(array $product): array
    {
        $states = [];

        if ($product['type'] === ProductDefinition::TYPE_DIGITAL || (int) $product['hasDownloads'] === 1) {
            $states[] = State::IS_DOWNLOAD;
        } else {
            $states[] = State::IS_PHYSICAL;
        }

        return $states;
    }
}
