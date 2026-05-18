<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Event;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\Log\Package;

/**
 * Allows extensions to enrich one DAL write payload after the import record
 * has been converted, but before the repository `upsert()` is executed.
 *
 * Typical extension use cases:
 * - add extra DAL fields that are not part of the base file mapping
 * - map extension-specific import values into custom entity payload fields
 * - fill derived values that are needed before the write happens
 *
 * The payload array is mutable through the public `$payload` property.
 * For nested changes, extensions can modify it directly:
 *
 * ```php
 * $event->payload['customFields']['myExtensionFlag'] = true;
 * ```
 */
#[Package('fundamentals@after-sales')]
class ImportPayloadBuiltEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly ImportExportV2ProfileEntity $profile,
        public readonly ImportExportRecord $record,
        public array $payload
    ) {
    }
}
