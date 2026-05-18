<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Json;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Format\ExportWriterInterface;
use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class JsonExportWriter implements ExportWriterInterface
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    /**
     * @param iterable<ImportExportRecord> $records
     */
    public function append(
        ImportExportV2ProfileEntity $profile,
        ImportExportV2FileEntity $file,
        iterable $records
    ): void {
        $chunkPayload = array_map(
            // The JSON file format is intentionally the plain record payload
            // without the internal `entity` / `payload` wrapper. Those wrapper
            // fields only exist inside the export pipeline.
            static fn (ImportExportRecord $record) => $record->payload,
            \is_array($records) ? $records : iterator_to_array($records)
        );

        if ($chunkPayload === []) {
            return;
        }

        $chunkBody = $this->encodeChunkBody($chunkPayload);

        $localFile = $this->createLocalWorkingCopy($file);

        try {
            // Files may live on non-local storage. We therefore copy the current
            // export file into a local temp file, perform a true in-place append
            // there, and then upload the finished file again.
            $this->appendChunkBody($localFile, $chunkBody);

            rewind($localFile);

            $this->writeStream($file, $localFile);
        } finally {
            fclose($localFile);
        }
    }

    /**
     * @return resource
     */
    private function createLocalWorkingCopy(ImportExportV2FileEntity $file)
    {
        $inputStream = $this->openReadStream($file);
        $localFile = tmpfile();

        if (!\is_resource($localFile)) {
            fclose($inputStream);

            throw new \RuntimeException('Failed to open a temporary file for JSON export.');
        }

        try {
            stream_copy_to_stream($inputStream, $localFile);
            rewind($localFile);
        } finally {
            fclose($inputStream);
        }

        return $localFile;
    }

    /**
     * @return resource
     */
    private function openReadStream(ImportExportV2FileEntity $file)
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        $stream = $this->filesystem->readStream($path);
        if (!\is_resource($stream)) {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        return $stream;
    }

    /**
     * @param list<array<string, mixed>> $chunkPayload
     */
    private function encodeChunkBody(array $chunkPayload): string
    {
        try {
            $encodedChunk = (string) json_encode($chunkPayload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        } catch (\JsonException $exception) {
            throw ImportExportV2Exception::invalidFormatContent('json', $exception->getMessage());
        }

        $trimmed = trim($encodedChunk);
        if ($trimmed === '[]') {
            return '';
        }

        return \substr($trimmed, 1, -1);
    }

    /**
     * @param resource $stream
     */
    private function appendChunkBody($stream, string $chunkBody): void
    {
        $streamSize = (int) (fstat($stream)['size'] ?? 0);
        if ($streamSize === 0) {
            fwrite($stream, '[' . $chunkBody . ']');

            return;
        }

        $closingBracketPosition = $this->findClosingBracketPosition($stream, $streamSize);
        $hasExistingRecords = $this->hasExistingRecords($stream, $closingBracketPosition);

        ftruncate($stream, $closingBracketPosition);
        fseek($stream, 0, \SEEK_END);

        if ($hasExistingRecords) {
            fwrite($stream, ',');
        }

        fwrite($stream, $chunkBody);
        fwrite($stream, ']');
    }

    /**
     * @param resource $stream
     */
    private function findClosingBracketPosition($stream, int $streamSize): int
    {
        for ($position = $streamSize - 1; $position >= 0; --$position) {
            fseek($stream, $position);
            $character = fgetc($stream);
            if (!\is_string($character) || $this->isJsonWhitespace($character)) {
                continue;
            }

            if ($character !== ']') {
                throw ImportExportV2Exception::invalidFormatContent('json', 'JSON export file must end with "]" before appending a chunk.');
            }

            return $position;
        }

        throw ImportExportV2Exception::invalidFormatContent('json', 'JSON export file must contain an array before appending a chunk.');
    }

    /**
     * @param resource $stream
     */
    private function hasExistingRecords($stream, int $closingBracketPosition): bool
    {
        for ($position = $closingBracketPosition - 1; $position >= 0; --$position) {
            fseek($stream, $position);
            $character = fgetc($stream);
            if (!\is_string($character) || $this->isJsonWhitespace($character)) {
                continue;
            }

            return $character !== '[';
        }

        throw ImportExportV2Exception::invalidFormatContent('json', 'JSON export file must contain an array before appending a chunk.');
    }

    /**
     * @param resource $stream
     */
    private function writeStream(ImportExportV2FileEntity $file, $stream): void
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        $this->filesystem->writeStream($path, $stream);
    }

    private function isJsonWhitespace(string $character): bool
    {
        return $character === ' '
            || $character === "\n"
            || $character === "\r"
            || $character === "\t";
    }
}
