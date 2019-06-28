<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

class XmlFileIterator implements RecordIterator, \SeekableIterator
{
    /**
     * @var \XMLReader
     */
    private $reader;

    /**
     * @var string
     */
    private $sourcePath;

    /**
     * @var int|null
     */
    private $index;

    public function __construct(string $sourcePath)
    {
        $this->reader = new \XMLReader();
        $this->sourcePath = $sourcePath;
    }

    public function current(): ?array
    {
        if (!$this->valid()) {
            return null;
        }

        return $this->toArray(new \SimpleXMLElement($this->reader->readOuterXml()));
    }

    public function next(): void
    {
        $this->reader->next('item');
        ++$this->index;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return $this->reader->name === 'item';
    }

    public function rewind(): void
    {
        $this->reader->open($this->sourcePath);
        // move to the first <item> node
        while ($this->reader->read() && $this->reader->name !== 'item');
        $this->index = 0;
    }

    public function count(): int
    {
        $previousIndex = $this->index;
        $this->rewind();
        $items = 0;

        while ($this->valid()) {
            $this->next();
            ++$items;
        }

        if ($previousIndex !== null) {
            $this->seek($previousIndex);
        }

        return $items;
    }

    /**
     * @param int $position
     */
    public function seek($position): void
    {
        if (!is_int($position)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid argument type. Expected: integer. Got: %s',
                gettype($position)
            ));
        }

        if (!$this->valid() || $this->index > $position) {
            $this->rewind();
        }

        while ($this->index < $position) {
            $this->next();
            if (!$this->valid()) {
                throw new \OverflowException(sprintf(
                    'Cannot seek to position %d. Reached end of XML document',
                    $position
                ));
            }
        }
    }

    private function toArray(\SimpleXMLElement $element): array
    {
        return json_decode(json_encode($element), true);
    }
}
