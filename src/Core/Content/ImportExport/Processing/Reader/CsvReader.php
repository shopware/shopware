<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Reader;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class CsvReader extends AbstractReader
{
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    private int $offset = 0;

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

    public function read(Config $config, $resource, int $offset): iterable
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('Argument $resource is not a resource');
        }

        $this->loadConfig($config);

        $this->setOffset($offset);

        while (!feof($resource)) {
            // if we start at a non-zero offset, we need to re-parse the header and then continue at offset
            if ($this->offset > 0 && $this->withHeader && $this->header === []) {
                $this->readSingleRecord($resource, 0);
            }

            $record = $this->readSingleRecord($resource, $this->offset);
            $this->setOffset(ftell($resource));

            if ($record !== null) {
                yield $record;
            }
        }
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @deprecated tag:v6.6.0 - reason:visibility-change - becomes private
     */
    public function loadConfig(Config $config): void
    {
        $this->delimiter = $config->get('delimiter') ?? $this->delimiter;
        $this->enclosure = $config->get('enclosure') ?? $this->enclosure;
        $this->withHeader = (bool) ($config->get('withHeader') ?? $this->withHeader);
        $this->escape = $config->get('escape') ?? $this->escape;
    }

    /**
     * @param resource $resource
     */
    private function handleBom($resource): void
    {
        $offset = ftell($resource);
        if ($offset !== 0) {
            return;
        }

        $bytes = fread($resource, 3);

        if ($bytes === self::BOM_UTF8) {
            return;
        }

        $this->seek($resource, $offset);
    }

    /**
     * @param resource $resource
     */
    private function readSingleRecord($resource, int $offset): ?array
    {
        $this->seek($resource, $offset);

        while (!feof($resource)) {
            $this->handleBom($resource);
            $record = fgetcsv($resource, 0, $this->delimiter, $this->enclosure, $this->escape);
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
    private function seek($resource, int $offset): void
    {
        $currentOffset = ftell($resource);
        if ($currentOffset !== $offset) {
            fseek($resource, $offset);
        }
    }

    private function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }
}
