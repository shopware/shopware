<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
class ImportResult
{
    /**
     * @param EntityWrittenContainerEvent[] $results
     * @param array<int, array<string, mixed>> $failedRecords
     */
    public function __construct(public readonly array $results, public readonly array $failedRecords)
    {
    }
}
