<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Event;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Run\ImportExportV2RunEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Allows extensions to modify the fully prepared export criteria before the
 * repository search is executed.
 *
 * At this point the criteria already contains:
 * - chunking information such as offset and limit
 * - the default export sorting
 * - associations derived from the profile record paths
 * - filters copied from the export run
 *
 * Typical extension use cases:
 * - add extra associations that are needed for custom export payload values
 * - add or refine DAL filters
 * - add additional sorting rules
 *
 * The profile, run, and mutable `Criteria` object are available through the
 * public properties `$profile`, `$run`, and `$criteria`.
 *
 * Example:
 *
 * ```php
 * $event->criteria->addAssociation('manufacturer.media');
 * ```
 */
#[Package('fundamentals@after-sales')]
class ExportCriteriaBuiltEvent
{
    public function __construct(
        public readonly ImportExportV2ProfileEntity $profile,
        public readonly ImportExportV2RunEntity $run,
        public readonly Criteria $criteria
    ) {
    }
}
