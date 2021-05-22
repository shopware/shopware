<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services\Archive;

abstract class Adapter implements \SeekableIterator, \Countable
{
    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $count;

    /**
     * @param int $position
     */
    public function seek($position): void
    {
        $this->position = $position;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        return $this->count > $this->position;
    }

    /**
     * @return array
     */
    public function each()
    {
        if (!$this->valid()) {
            return [];
        }
        $result = [$this->key(), $this->current()];
        $this->next();

        return $result;
    }
}
