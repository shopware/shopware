<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Archive;

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
        $this->position = (int) $position;
    }

    public function count()
    {
        return $this->count;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function key()
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->count > $this->position;
    }

    /**
     * @return array|bool
     */
    public function each()
    {
        if (!$this->valid()) {
            return false;
        }
        $result = [$this->key(), $this->current()];
        $this->next();

        return $result;
    }
}
