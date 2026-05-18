<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
final class ImportExportRecord
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $entity,
        public array $payload
    ) {
    }
}
