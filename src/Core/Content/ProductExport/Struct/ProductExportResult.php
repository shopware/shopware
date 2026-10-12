<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

use Shopware\Core\Content\ProductExport\Error\Error;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductExportResult
{
    /**
     * @param list<Error> $errors
     */
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'total', description: 'The export no longer computes a grand total; use hasNextBatch() and getOffset() instead.')]
    public function __construct(
        private readonly string $content,
        private readonly array $errors,
        private readonly int $total = 0,
        private readonly int $offset = 0,
        private readonly bool $hasNextBatch = false
    ) {
        if ($total !== 0) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing $total is deprecated');
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return list<Error>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @deprecated tag:v6.8.0 - No longer reflects the grand total of matching products.
     * With keyset pagination the export never computes an exact count; this returns the
     * number of products fetched in the last batch. Use hasNextBatch() for pagination.
     */
    public function getTotal(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->total;
    }

    /**
     * Resume position for the next partial-generation batch (an autoIncrement keyset cursor when the
     * export is unsorted, otherwise a row offset).
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * True when the last batch returned a full buffer, i.e. another batch should follow.
     */
    public function hasNextBatch(): bool
    {
        return $this->hasNextBatch;
    }
}
