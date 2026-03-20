<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class JsonlRowParser
{
    /**
     * @return list<array{line:int, row:array<string, mixed>}>
     *
     * @throws JsonlParsingException
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\R/', $content);

        if ($lines === false) {
            throw new JsonlParsingException('The JSONL export could not be split into lines.', 1);
        }

        $decodedRows = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new JsonlParsingException($exception->getMessage(), $lineNumber + 1, $exception);
            }

            if (!\is_array($decoded)) {
                throw new JsonlParsingException('Each JSONL line must decode to an object.', $lineNumber + 1);
            }

            $decodedRows[] = [
                'line' => $lineNumber + 1,
                'row' => $decoded,
            ];
        }

        return $decodedRows;
    }
}
