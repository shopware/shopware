<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Struct;

use Shopware\Core\Content\ProductExport\Error\Error;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductExportResult
{
    /**
     * @param list<Error> $errors
     */
    public function __construct(
        private readonly string $content,
        private readonly array $errors,
        private readonly int $total,
        private readonly int $lastId = 0,
        private readonly bool $hasNextBatch = false
    ) {
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
        return $this->total;
    }

    /**
     * Highest product.autoIncrement processed so far — the keyset cursor for the next batch.
     */
    public function getLastId(): int
    {
        return $this->lastId;
    }

    /**
     * True when the last batch returned a full buffer, i.e. another batch should follow.
     */
    public function hasNextBatch(): bool
    {
        return $this->hasNextBatch;
    }
}
