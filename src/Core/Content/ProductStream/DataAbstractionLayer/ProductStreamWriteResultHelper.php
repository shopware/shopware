<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\DataAbstractionLayer;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
final class ProductStreamWriteResultHelper
{
    /**
     * @return list<string>
     */
    public static function getAffectedStreamIds(EntityWrittenContainerEvent $event): array
    {
        return self::getAffectedStreamIdsFromEvent(
            $event->getEventByEntityName(ProductStreamFilterDefinition::ENTITY_NAME)
        );
    }

    /**
     * @return list<string>
     */
    public static function getAffectedStreamIdsFromEvent(?EntityWrittenEvent $event): array
    {
        if ($event === null) {
            return [];
        }

        $streamIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            foreach (self::collectStreamIds($writeResult) as $streamId) {
                $streamIds[$streamId] = true;
            }
        }

        return array_keys($streamIds);
    }

    /**
     * @return list<string>
     */
    private static function collectStreamIds(EntityWriteResult $writeResult): array
    {
        $ids = [];

        $payload = $writeResult->getPayload();
        $payloadId = self::normalizeStreamId(
            $payload['productStreamId'] ?? $payload['product_stream_id'] ?? null
        );
        if ($payloadId !== null) {
            $ids[] = $payloadId;
        }

        $state = $writeResult->getExistence()?->getState();
        $stateId = self::normalizeStreamId(
            $state['product_stream_id'] ?? $state['productStreamId'] ?? null
        );
        if ($stateId !== null) {
            $ids[] = $stateId;
        }

        return $ids;
    }

    private static function normalizeStreamId(mixed $streamId): ?string
    {
        if (!\is_string($streamId) || $streamId === '') {
            return null;
        }

        if (Uuid::isValid($streamId)) {
            return $streamId;
        }

        if (\strlen($streamId) === 16) {
            return Uuid::fromBytesToHex($streamId);
        }

        return null;
    }
}
