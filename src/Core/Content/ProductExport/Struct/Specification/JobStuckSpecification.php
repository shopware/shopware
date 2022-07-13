<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct\Specification;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

class JobStuckSpecification implements SpecificationInterface
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

    public function isSatisfiedBy($value): bool
    {
        if (!$value instanceof ProductExportEntity) {
            throw new \LogicException(
                \sprintf(
                    'ExportJobStuckSpecification requires %s as argument, %s is given.',
                    ProductExportEntity::class,
                    gettype($value)
                )
            );
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $exportUpdatedAt = $value->getUpdatedAt() ?? $now;

        return $value->isPausedSchedule()
            && $now->getTimestamp() >= $exportUpdatedAt->getTimestamp() + $this->maxIdleTimeout;
    }
}
