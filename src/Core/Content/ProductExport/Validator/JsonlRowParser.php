<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class JsonlRowParser
{
    /**
     * @throws ProductExportException
     *
     * @return list<array{line:int, row:array<string, mixed>}>
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\R/', $content);
        \assert($lines !== false);

        $decodedRows = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ProductExportException::malformedJsonlLine($exception->getMessage(), $lineNumber + 1);
            }

            if (!\is_array($decoded)) {
                throw ProductExportException::jsonlLineMustDecodeToObject($lineNumber + 1);
            }

            $decodedRows[] = [
                'line' => $lineNumber + 1,
                'row' => $decoded,
            ];
        }

        return $decodedRows;
    }
}
