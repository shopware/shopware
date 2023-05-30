<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductStatesBeforeChangeEvent;
use Shopware\Core\Content\Product\Events\ProductStatesChangedEvent;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
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
     * @param string[] $ids
     */
    public function update(array $ids, Context $context): void
    {
        $sql = 'SELECT LOWER(HEX(`product`.`id`)) as id,
                IF(`product_download`.`id` IS NOT NULL, 1, 0) as hasDownloads,
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
            ['ids' => ArrayParameterType::STRING]
        );

        $updates = [];
        foreach ($products as $product) {
            $newStates = $this->getNewStates($product);
            $oldStates = $product['states'] ? json_decode((string) $product['states'], true, 512, \JSON_THROW_ON_ERROR) : [];

            if (\count(array_diff($newStates, $oldStates)) === 0) {
                continue;
            }

            $updates[] = new UpdatedStates($product['id'], $oldStates, $newStates);
        }

        if (empty($updates)) {
            return;
        }

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `product` SET `states` = :states WHERE `id` = :id AND `version_id` = :version')
        );

        $event = new ProductStatesBeforeChangeEvent($updates, $context);
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getUpdatedStates() as $updatedStates) {
            $query->execute([
                'states' => json_encode($updatedStates->getNewStates(), \JSON_THROW_ON_ERROR),
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

        if ((int) $product['hasDownloads'] === 1) {
            $states[] = State::IS_DOWNLOAD;
        } else {
            $states[] = State::IS_PHYSICAL;
        }

        return $states;
    }
}
