<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Event;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * Allows extensions to enrich one exported record after the DAL entity has
 * been converted into the shared import/export record shape, but before the
 * JSON or CSV writer serializes it.
 *
 * Typical extension use cases:
 * - add additional payload values that are not part of the base profile
 * - derive computed export fields from the loaded root entity
 * - normalize or rename payload values before the format writer sees them
 *
 * The profile, root entity, and mutable record are available through the
 * public properties `$profile`, `$entity`, and `$record`.
 *
 * The record object is mutable. Extensions can modify it directly, for
 * example:
 *
 * ```php
 * $event->record->payload['customFields']['myExtensionFlag'] = true;
 * ```
 */
#[Package('fundamentals@after-sales')]
class ExportRecordConvertedEvent
{
    public function __construct(
        public readonly ImportExportV2ProfileEntity $profile,
        public readonly Entity $entity,
        public readonly ImportExportRecord $record
    ) {
    }
}
