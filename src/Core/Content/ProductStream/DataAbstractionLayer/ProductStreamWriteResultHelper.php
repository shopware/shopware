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
            $streamId = self::resolveStreamId($writeResult);
            if ($streamId === null) {
                continue;
            }

            $streamIds[$streamId] = true;
        }

        return array_keys($streamIds);
    }

    private static function resolveStreamId(EntityWriteResult $writeResult): ?string
    {
        $payload = $writeResult->getPayload();

        $streamId = self::normalizeStreamId(
            $payload['productStreamId'] ?? $payload['product_stream_id'] ?? null
        );

        if ($streamId !== null) {
            return $streamId;
        }

        $existence = $writeResult->getExistence();
        if ($existence === null) {
            return null;
        }

        $state = $existence->getState();

        return self::normalizeStreamId(
            $state['product_stream_id'] ?? $state['productStreamId'] ?? null
        );
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
