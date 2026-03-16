<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\JsonlValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class JsonlValidator implements ValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void
    {
        if ($productExportEntity->getFileFormat() !== ProductExportEntity::FILE_FORMAT_JSONL) {
            return;
        }

        $this->validateJsonl($productExportEntity, $productExportContent, $errors);
    }

    private function validateJsonl(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void
    {
        $lines = preg_split('/\R/', $productExportContent);

        if ($lines === false) {
            $errors->add(new JsonlValidationError($productExportEntity->getId(), 'The JSONL export could not be split into lines.'));

            return;
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $currentNumber = $lineNumber + 1;
                $message = \sprintf('Invalid JSONL at line %s:%s', $currentNumber, $exception->getMessage());

                $errors->add(new JsonlValidationError($productExportEntity->getId(), $message));

                return;
            }
        }
    }
}
