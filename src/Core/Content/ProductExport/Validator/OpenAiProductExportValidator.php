<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\JsonlValidationError;
use Shopware\Core\Content\ProductExport\Error\ProviderValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class OpenAiProductExportValidator extends AbstractProviderValidator
{
    /**
     * @var list<string>
     */
    private const ALLOWED_AVAILABILITY_VALUES = [
        'in_stock',
        'out_of_stock',
        'backorder',
        'pre_order',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly JsonlRowParser $jsonlRowParser,
    ) {
    }

    protected function getProviderTechnicalName(): string
    {
        return 'open-ai';
    }

    protected function validateProviderExport(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void
    {
        if ($productExportEntity->getFileFormat() !== ProductExportEntity::FILE_FORMAT_JSONL) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                'file_format',
                'OpenAI product exports must use the "jsonl" file format.'
            ));

            return;
        }

        try {
            $rows = $this->parseJsonlRows($productExportContent);
        } catch (ProductExportException $exception) {
            $errors->add(new JsonlValidationError(
                $productExportEntity->getId(),
                $exception->getMessage(),
                (int) ($exception->getParameter('line') ?? 1)
            ));

            return;
        }

        $itemIds = [];

        foreach ($rows as ['line' => $line, 'row' => $row]) {
            $this->validateNonEmptyString($productExportEntity, $errors, $row, 'item_id', $line);
            $this->validateNonEmptyString($productExportEntity, $errors, $row, 'title', $line);
            $this->validateNonEmptyString($productExportEntity, $errors, $row, 'description', $line);
            $this->validateNonEmptyString($productExportEntity, $errors, $row, 'brand', $line);
            $this->validateNonEmptyString($productExportEntity, $errors, $row, 'seller_name', $line);
            $this->validateCountryCode($productExportEntity, $errors, $row, 'store_country', $line);
            $this->validateUrl($productExportEntity, $errors, $row, 'url', $line);
            $this->validateUrl($productExportEntity, $errors, $row, 'image_url', $line);
            $this->validateUrl($productExportEntity, $errors, $row, 'seller_url', $line);
            $this->validateUrl($productExportEntity, $errors, $row, 'return_policy', $line);
            $this->validatePrice($productExportEntity, $errors, $row, 'price', $line);

            if (\array_key_exists('sale_price', $row)) {
                $this->validatePrice($productExportEntity, $errors, $row, 'sale_price', $line);
            }

            $this->validateBoolean($productExportEntity, $errors, $row, 'is_eligible_search', $line);
            $this->validateBoolean($productExportEntity, $errors, $row, 'is_eligible_checkout', $line);
            $this->validateBoolean($productExportEntity, $errors, $row, 'listing_has_variations', $line);

            if (($row['listing_has_variations'] ?? null) === true) {
                $this->validateNonEmptyString($productExportEntity, $errors, $row, 'group_id', $line);
            }

            $this->validateAvailability($productExportEntity, $errors, $row, $line);
            $this->validateTargetCountries($productExportEntity, $errors, $row, $line);

            if (isset($row['item_id']) && \is_string($row['item_id']) && $row['item_id'] !== '') {
                if (isset($itemIds[$row['item_id']])) {
                    $errors->add(new ProviderValidationError(
                        $productExportEntity->getId(),
                        $this->getProviderTechnicalName(),
                        'item_id',
                        \sprintf('The item_id "%s" is not unique in the feed.', $row['item_id']),
                        $line
                    ));
                }

                $itemIds[$row['item_id']] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validateNonEmptyString(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, string $field, int $line): void
    {
        $value = $row[$field] ?? null;

        if (!\is_string($value) || trim($value) === '') {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                $field,
                \sprintf('The field "%s" must be a non-empty string.', $field),
                $line
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validateUrl(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, string $field, int $line): void
    {
        $value = $row[$field] ?? null;

        if (!\is_string($value) || filter_var($value, \FILTER_VALIDATE_URL) === false) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                $field,
                \sprintf('The field "%s" must be a valid absolute URL.', $field),
                $line
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validatePrice(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, string $field, int $line): void
    {
        $value = $row[$field] ?? null;

        if (!\is_string($value) || preg_match('/^\d+(?:\.\d+)? [A-Z]{3}$/', $value) !== 1) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                $field,
                \sprintf('The field "%s" must be formatted as "<number> <ISO-4217>".', $field),
                $line
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validateBoolean(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, string $field, int $line): void
    {
        if (!\is_bool($row[$field] ?? null)) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                $field,
                \sprintf('The field "%s" must be a boolean.', $field),
                $line
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validateCountryCode(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, string $field, int $line): void
    {
        $value = $row[$field] ?? null;

        if (!\is_string($value) || preg_match('/^[A-Z]{2}$/', $value) !== 1) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                $field,
                \sprintf('The field "%s" must be a 2-letter upper-case ISO country code.', $field),
                $line
            ));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validateTargetCountries(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, int $line): void
    {
        $value = $row['target_countries'] ?? null;

        if (!\is_array($value) || $value === []) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                'target_countries',
                'The field "target_countries" must be a non-empty array of ISO country codes.',
                $line
            ));

            return;
        }

        foreach ($value as $countryCode) {
            if (!\is_string($countryCode) || preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'target_countries',
                    'Each target country must be a 2-letter upper-case ISO country code.',
                    $line
                ));

                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function validateAvailability(ProductExportEntity $productExportEntity, ErrorCollection $errors, array $row, int $line): void
    {
        $value = $row['availability'] ?? null;

        if (!\is_string($value) || !\in_array($value, self::ALLOWED_AVAILABILITY_VALUES, true)) {
            $errors->add(new ProviderValidationError(
                $productExportEntity->getId(),
                $this->getProviderTechnicalName(),
                'availability',
                'The field "availability" must be one of: in_stock, out_of_stock, backorder, pre_order.',
                $line
            ));

            return;
        }

        if ($value === 'pre_order') {
            $availabilityDate = $row['availability_date'] ?? null;

            if (!\is_string($availabilityDate) || trim($availabilityDate) === '') {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'availability_date',
                    'The field "availability_date" is required when availability is "pre_order".',
                    $line
                ));

                return;
            }

            try {
                new \DateTimeImmutable($availabilityDate);
            } catch (\Exception) {
                $errors->add(new ProviderValidationError(
                    $productExportEntity->getId(),
                    $this->getProviderTechnicalName(),
                    'availability_date',
                    'The field "availability_date" must be a valid date string.',
                    $line
                ));
            }
        }
    }

    /**
     * @throws ProductExportException
     *
     * @return list<array{line:int, row:array<string, mixed>}>
     */
    private function parseJsonlRows(string $productExportContent): array
    {
        return $this->jsonlRowParser->parse($productExportContent);
    }
}
