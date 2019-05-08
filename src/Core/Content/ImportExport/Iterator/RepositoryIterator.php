<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class RepositoryIterator implements RecordIterator
{
    private const BUFFER_SIZE = 100;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array|null
     */
    private $buffer;

    /**
     * @var int|null
     */
    private $index;

    /**
     * @var int|null
     */
    private $total;

    public function __construct(EntityRepositoryInterface $repository, Context $context)
    {
        $this->repository = $repository;
        $this->context = $context;
    }

    public function current(): ?array
    {
        if (!isset($this->buffer[$this->index])) {
            $this->fetchData();
        }

        return $this->buffer[$this->index] ?? null;
    }

    public function next(): void
    {
        ++$this->index;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return $this->index < $this->total;
    }

    public function rewind(): void
    {
        $this->index = 0;
        $this->total = $this->count();
    }

    public function count(): int
    {
        $criteria = $this->getCriteria();
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->repository->search($criteria, $this->context)->getTotal();
    }

    private function fetchData(): void
    {
        $criteria = $this->getCriteria();
        $criteria->setOffset($this->index);
        $criteria->setLimit(static::BUFFER_SIZE);
        $key = $this->index;
        $this->buffer = [];
        foreach ($this->repository->search($criteria, $this->context)->getEntities() as $entity) {
            /* @var Entity $entity */
            $this->buffer[$key] = json_decode(json_encode($entity), true);
            ++$key;
        }
    }

    private function getCriteria(): Criteria
    {
        return new Criteria();
    }
}
