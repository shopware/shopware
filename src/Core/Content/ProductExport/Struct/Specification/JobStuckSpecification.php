<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct\Specification;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

class JobStuckSpecification
{
    /**
     * Maximum job idle time to be satisfied be stuck specification in seconds.
     *
     * @var int
     */
    private int $maxIdleTimeout;

    public function __construct(
        int $maxIdleTimeout
    ) {
       $this->maxIdleTimeout = $maxIdleTimeout;
    }

    public function isSatisfiedBy(ProductExportEntity $candidate): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $exportUpdatedAt = $candidate->getUpdatedAt() ?? $now;

        return $candidate->isPausedSchedule()
            && $now->getTimestamp() >= $exportUpdatedAt->getTimestamp() + $this->maxIdleTimeout;
    }
}
