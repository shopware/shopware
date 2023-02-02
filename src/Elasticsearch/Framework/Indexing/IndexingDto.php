<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

class IndexingDto
{
    protected array $ids;

    protected string $index;

    protected string $entity;

    public function __construct(array $ids, string $index, string $entity)
    {
        $this->ids = array_values($ids);
        $this->index = $index;
        $this->entity = $entity;
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
