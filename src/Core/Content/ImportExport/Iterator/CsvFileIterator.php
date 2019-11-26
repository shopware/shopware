<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

class CsvFileIterator implements RecordIterator, \SeekableIterator
{
    /**
     * @var int
     */
    private $index;

    /**
     * @var resource
     */
    private $resource;

    /**
     * @var array|null
     */
    private $currentRow;

    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var string
     */
    private $enclosure;

    /**
     * @var array
     */
    private $header;

    /**
     * @var int
     */
    private $columns;

    /**
     * @param resource $resource
     */
    public function __construct($resource, string $delimiter = ';', string $enclosure = '"')
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Argument $resource is not a resource');
        }
        $this->index = 0;
        $this->resource = $resource;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    public function current(): array
    {
        return $this->currentRow;
    }

    public function next(): void
    {
        ++$this->index;

        $line = $this->parseLine();
        if (!is_array($line)) {
            $this->currentRow = null;

            return;
        }

        if (count($line) !== $this->columns) {
            throw new \RuntimeException('Invalid CSV file. Number of columns mismatch in line ' . ($this->index + 1));
        }
        $this->currentRow = array_combine($this->header, $line);
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return !feof($this->resource) && is_array($this->currentRow);
    }

    public function rewind(): void
    {
        rewind($this->resource);
        $line = $this->parseLine();
        if (!is_array($line)) {
            throw new \RuntimeException('Invalid CSV file. Missing header');
        }

        $this->header = $line;
        $this->columns = count($this->header);
        $this->index = -1;
        $this->next();
    }

    public function seek($position): void
    {
        if ($this->index === $position) {
            return;
        }

        if ($this->index > $position - 1) {
            $this->rewind();
        }
        while ($this->index < $position - 1) {
            $this->skip();
        }
        $this->next();
    }

    /**
     * Counts the lines of the CSV file excluding header line
     *
     * Please note that this method will read the whole file line-by-line on each method call.
     * Do not invoke this method more often than necessary
     */
    public function count(): int
    {
        $previousPosition = ftell($this->resource);
        rewind($this->resource);
        $count = 0;
        while (!feof($this->resource)) {
            $line = fgets($this->resource);
            if (is_string($line) && !empty(trim($line))) {
                ++$count;
            }
        }
        fseek($this->resource, $previousPosition);

        return $count > 0 ? $count - 1 : $count;
    }

    private function parseLine(): ?array
    {
        $line = fgetcsv($this->resource, 0, $this->delimiter, $this->enclosure);
        if (!is_array($line)) {
            return null;
        }

        return $line[0] !== null ? $line : [];
    }

    private function skip(): void
    {
        if (!$this->valid()) {
            throw new \OverflowException(sprintf('Cannot leave position %d. Reached end of file.', $this->index));
        }

        $line = fgets($this->resource);
        if ($line === false) {
            throw new \RuntimeException(sprintf('Cannot leave position %d. An error occurred.', $this->index));
        }

        ++$this->index;
    }
}
