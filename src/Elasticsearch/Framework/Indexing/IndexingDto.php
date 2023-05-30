<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class IndexingDto
{
    protected array $ids;

    public function __construct(
        array $ids,
        protected string $index,
        protected string $entity
    ) {
        $this->ids = array_values($ids);
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }
}
