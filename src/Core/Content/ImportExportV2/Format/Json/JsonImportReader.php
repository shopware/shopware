<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Json;

use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Format\ImportReaderInterface;
use Shopware\Core\Content\ImportExportV2\Format\ReadResult;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Content\ImportExportV2\Support\FileService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class JsonImportReader implements ImportReaderInterface
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function readChunk(
        ImportExportV2FileEntity $file,
        ImportExportV2ProfileEntity $profile,
        int $offset,
        int $limit,
        ?int $nextByteOffset = null
    ): ReadResult {
        $offset = max(0, $offset);
        $limit = max(1, $limit);

        $checkpoint = $this->resolveCheckpoint($offset, $nextByteOffset);

        // Chunked imports need stable local file semantics like fseek/ftell so
        // later chunks can resume from one persisted byte offset. We therefore
        // create a single local working copy and reuse it for all later chunks
        $localFile = fopen($this->fileService->getOrCreateLocalWorkingCopyPath($file), 'rb');
        if (!\is_resource($localFile)) {
            throw new \RuntimeException('Failed to open the local JSON working copy.');
        }

        try {
            $stream = $localFile;
            if ($checkpoint !== null) {
                fseek($stream, $checkpoint);
            } else {
                rewind($stream);
            }

            $records = [];
            $recordIndex = $checkpoint !== null ? $offset : 0;
            $isRescanningFromStart = $checkpoint === null && $offset > 0;
            $hasMore = false;
            $totalRecords = null;
            $nextByteOffset = null;

            foreach ($this->scanRecordJsonStream($stream, $checkpoint === null) as $recordChunk) {
                $recordStartOffset = $recordChunk['start'];
                $recordJson = $recordChunk['json'];

                // Without a persisted byte checkpoint we have to rescan from
                // the beginning and skip records until we reach the requested
                // logical offset.
                if ($isRescanningFromStart && $recordIndex < $offset) {
                    ++$recordIndex;

                    continue;
                }

                if (\count($records) < $limit) {
                    $records[] = $this->decodeRecord($recordJson, $profile);
                    ++$recordIndex;
                    $nextByteOffset = ftell($stream);

                    continue;
                }

                // We only need one additional record to know that another chunk exists.
                $hasMore = true;
                $nextByteOffset = $recordStartOffset;

                break;
            }

            if (!$hasMore) {
                $totalRecords = $recordIndex;
            }

            return new ReadResult(
                $records,
                $hasMore,
                $totalRecords,
                $hasMore ? $nextByteOffset : null
            );
        } finally {
            fclose($localFile);
        }
    }

    /**
     * The JSON file is scanned one top-level object at a time so chunked imports
     * do not need to decode the full array into memory first.
     *
     * The optional checkpoint lets later chunks continue from the next unread
     * record instead of rescanning the whole array from byte 0.
     *
     * @return \Generator<int, array{start:int,json:string}>
     */
    private function scanRecordJsonStream($stream, bool $fromArrayStart): \Generator
    {
        if ($fromArrayStart) {
            $arrayStart = $this->readNextNonWhitespaceCharacter($stream);
            if ($arrayStart !== '[') {
                throw ImportExportV2Exception::invalidFormatContent('json', 'Expected a JSON array of records.');
            }
        }

        while (true) {
            $character = $this->readNextCharacterOutsideWhitespaceAndDelimiters($stream);
            if ($character === null) {
                break;
            }

            if ($character === ']') {
                return;
            }

            if ($character !== '{') {
                throw ImportExportV2Exception::invalidFormatContent('json', 'Every record must be an object.');
            }

            yield [
                'start' => ftell($stream) - 1,
                'json' => $this->readObjectJson($stream),
            ];
        }

        throw ImportExportV2Exception::invalidFormatContent('json', 'JSON array is not properly closed.');
    }

    /**
     * The run already stores the logical import progress in `offset`. The only
     * format-specific resume state we still need is the physical byte position
     * of the next unread record in the source file.
     */
    private function resolveCheckpoint(int $offset, ?int $nextByteOffset): ?int
    {
        if ($offset === 0) {
            return null;
        }

        if (!\is_int($nextByteOffset) || $nextByteOffset < 0) {
            return null;
        }

        return $nextByteOffset;
    }

    private function readObjectJson($stream): string
    {
        $recordJson = '{';
        $depth = 1;
        $inString = false;
        $isEscaped = false;

        while (($character = fgetc($stream)) !== false) {
            \assert(\is_string($character));
            $recordJson .= $character;

            if ($inString) {
                if ($isEscaped) {
                    $isEscaped = false;

                    continue;
                }

                if ($character === '\\') {
                    $isEscaped = true;

                    continue;
                }

                if ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === '{' || $character === '[') {
                ++$depth;

                continue;
            }

            if ($character !== '}' && $character !== ']') {
                continue;
            }

            --$depth;
            if ($depth === 0) {
                return $recordJson;
            }
        }

        throw ImportExportV2Exception::invalidFormatContent('json', 'JSON object is not properly closed.');
    }

    private function decodeRecord(string $recordJson, ImportExportV2ProfileEntity $profile): ImportExportRecord
    {
        try {
            $record = json_decode($recordJson, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ImportExportV2Exception::invalidFormatContent('json', $exception->getMessage());
        }

        if (!\is_array($record)) {
            throw ImportExportV2Exception::invalidFormatContent('json', 'Every record must be an object.');
        }

        // The JSON file format uses the plain payload shape:
        //
        // ```json
        // {
        //   "productNumber": "SW10001",
        //   "tax": { "id": "tax-123" }
        // }
        // ```
        //
        // Internally, the import/export pipeline still expects the shared
        // record wrapper:
        //
        // ```php
        // [
        //     'entity' => 'product',
        //     'payload' => [...],
        // ]
        // ```
        //
        // So the reader wraps plain JSON objects here before validation and
        // further processing.
        if (\array_key_exists('payload', $record)) {
            $entity = \is_string($record['entity'] ?? null) ? $record['entity'] : $profile->getEntity();
            $payload = \is_array($record['payload']) ? $record['payload'] : [];

            return new ImportExportRecord($entity, $payload);
        }

        return new ImportExportRecord($profile->getEntity(), $record);
    }

    private function readNextNonWhitespaceCharacter($stream): ?string
    {
        while (($character = fgetc($stream)) !== false) {
            \assert(\is_string($character));

            if (!$this->isJsonWhitespace($character)) {
                return $character;
            }
        }

        return null;
    }

    private function readNextCharacterOutsideWhitespaceAndDelimiters($stream): ?string
    {
        while (($character = fgetc($stream)) !== false) {
            \assert(\is_string($character));

            if ($character === ',' || $this->isJsonWhitespace($character)) {
                continue;
            }

            return $character;
        }

        return null;
    }

    private function isJsonWhitespace(string $character): bool
    {
        return $character === ' '
            || $character === "\n"
            || $character === "\r"
            || $character === "\t";
    }
}
