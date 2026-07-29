<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Reader;

use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class CsvReader extends AbstractReader
{
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    // Use 8 KB chunk when skipping forward on non-seekable streams.
    private const SEEK_CHUNK_SIZE = 8192;

    private int $offset = 0;

    /**
     * @var array<mixed>
     */
    private array $header = [];

    /**
     * @internal
     */
    public function __construct(
        private string $delimiter = ';',
        private string $enclosure = '"',
        private string $escape = '\\',
        private bool $withHeader = true
    ) {
    }

    /**
     * @param resource $resource
     *
     * @return iterable<array<mixed>>
     */
    public function read(Config $config, $resource, int $offset): iterable
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('Argument $resource is not a resource');
        }

        $this->loadConfig($config);

        $this->setOffset($offset);

        if ($this->withHeader && $this->header === []) {
            $this->initializeHeader($resource);
        }

        $currentOffset = max($this->offset, $this->getCurrentOffset($resource));
        $this->setOffset($currentOffset);
        $this->moveToOffset($resource, $currentOffset);

        while (!feof($resource)) {
            $record = $this->readSingleRecord($resource);
            $currentOffset = $this->getCurrentOffset($resource);

            $this->setOffset($currentOffset);

            if ($record !== null) {
                yield $record;
            }
        }
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    private function loadConfig(Config $config): void
    {
        $this->delimiter = $config->get('delimiter') ?? $this->delimiter;
        $this->enclosure = $config->get('enclosure') ?? $this->enclosure;
        $this->withHeader = (bool) ($config->get('withHeader') ?? $this->withHeader);
        $this->escape = $config->get('escape') ?? $this->escape;
    }

    /**
     * @param resource $resource
     *
     * @return array<mixed>|null
     */
    private function readSingleRecord($resource): ?array
    {
        while (!feof($resource)) {
            $record = $this->readRecord($resource);
            // skip if it's an empty line
            if ($record === false || (\count($record) === 1 && $record[0] === null)) {
                continue;
            }

            $record = $this->mapRecord($record);

            // skip empty
            if ($record === null || array_filter($record) === []) {
                continue;
            }

            return $record;
        }

        return null;
    }

    /**
     * @param resource $resource
     */
    private function initializeHeader($resource): void
    {
        while (!feof($resource)) {
            $record = $this->readRecord($resource);

            if ($record === false || (\count($record) === 1 && $record[0] === null)) {
                continue;
            }

            $this->header = $record;

            return;
        }
    }

    /**
     * @param array<mixed> $record
     *
     * @return array<mixed>|null
     */
    private function mapRecord(array $record): ?array
    {
        if (!$this->withHeader) {
            return $record;
        }

        // get header and read next line
        if ($this->header === []) {
            $this->header = $record;

            return null;
        }

        $assoc = [];
        foreach ($this->header as $idx => $key) {
            $assoc[$key] = $record[$idx] ?? '';
        }

        return $assoc;
    }

    /**
     * @param resource $resource
     */
    private function moveToOffset($resource, int $offset): void
    {
        $currentOffset = $this->getCurrentOffset($resource);
        if ($currentOffset === $offset) {
            return;
        }

        $metaData = stream_get_meta_data($resource);
        if ($currentOffset > $offset) {
            if ($metaData['seekable'] !== true) {
                throw ImportExportException::processingError('Cannot rewind a non-seekable csv stream.');
            }

            fseek($resource, $offset);

            return;
        }

        if ($metaData['seekable'] === true) {
            fseek($resource, $offset);

            return;
        }

        $remainingBytes = $offset - $currentOffset;
        while ($remainingBytes > 0 && !feof($resource)) {
            $skipSize = min($remainingBytes, self::SEEK_CHUNK_SIZE);
            $chunk = fread($resource, $skipSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $remainingBytes -= \strlen($chunk);
        }
    }

    /**
     * @param resource $resource
     *
     * @return array<mixed>|false
     */
    private function readRecord($resource): array|false
    {
        $isStartOfStream = $this->getCurrentOffset($resource) === 0;
        $record = fgetcsv($resource, 0, $this->delimiter, $this->enclosure, $this->escape);

        if ($record === false || !$isStartOfStream || !isset($record[0]) || !\is_string($record[0])) {
            return $record;
        }

        $record[0] = preg_replace('/^' . preg_quote(self::BOM_UTF8, '/') . '/', '', $record[0]) ?? $record[0];

        return $record;
    }

    /**
     * @param resource $resource
     */
    private function getCurrentOffset($resource): int
    {
        $offset = ftell($resource);

        if ($offset === false) {
            return 0;
        }

        return $offset;
    }

    private function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }
}
