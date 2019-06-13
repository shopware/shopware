<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

class CsvFileIterator implements RecordIterator
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
        $line = $this->readLine();
        if (is_array($line) && count($line) !== $this->columns) {
            throw new \RuntimeException('Invalid CSV file. Number of columns mismatch in line ' . ($this->index + 2));
        }
        $this->currentRow = is_array($line) ? array_combine($this->header, $line) : $line;
        ++$this->index;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return is_array($this->currentRow);
    }

    public function rewind(): void
    {
        rewind($this->resource);
        $this->header = $this->readLine();
        if (is_array($this->header)) {
            $this->columns = count($this->header);
        } else {
            throw new \RuntimeException('Invalid CSV file. Missing header');
        }
        $this->index = 0;
        $line = $this->readLine();
        $this->currentRow = is_array($line) ? array_combine($this->header, $line) : $line;
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

    private function readLine(): ?array
    {
        $line = fgetcsv($this->resource, 0, $this->delimiter, $this->enclosure);
        if (!$line) {
            return null;
        }

        return $line;
    }
}
