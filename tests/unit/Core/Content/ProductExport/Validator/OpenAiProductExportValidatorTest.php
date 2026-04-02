<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\JsonlValidationError;
use Shopware\Core\Content\ProductExport\Error\ProviderValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Validator\JsonlRowParser;
use Shopware\Core\Content\ProductExport\Validator\OpenAiProductExportValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(OpenAiProductExportValidator::class)]
class OpenAiProductExportValidatorTest extends TestCase
{
    public function testValidateDoesNothingForOtherProviders(): void
    {
        $entity = $this->createProductExportEntity();
        $entity->setProvider('google');

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, 'not-jsonl', $errors);

        static::assertCount(0, $errors);
    }

    public function testValidateAddsErrorWhenFileFormatIsNotJsonl(): void
    {
        $entity = $this->createProductExportEntity();
        $entity->setFileFormat(ProductExportEntity::FILE_FORMAT_XML);

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, '', $errors);

        static::assertCount(1, $errors);

        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('file_format', $error->getParameters()['field']);
    }

    public function testValidateAddsErrorForMissingRequiredUrlField(): void
    {
        $entity = $this->createProductExportEntity();

        $content = json_encode([
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'item_id' => 'SKU-1',
            'title' => 'Example',
            'description' => 'Example description',
            'url' => 'https://example.com/product',
            'brand' => 'ACME',
            'image_url' => 'https://example.com/image.jpg',
            'price' => '10.99 EUR',
            'availability' => 'in_stock',
            'group_id' => 'group-1',
            'listing_has_variations' => false,
            'seller_name' => 'Merchant',
            'seller_url' => 'https://example.com',
            'target_countries' => ['DE'],
            'store_country' => 'DE',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $firstError = $errors->first();

        static::assertNotNull($firstError);
        static::assertSame('provider-validation-failed', $firstError->getMessageKey());
        static::assertSame('return_policy', $firstError->getParameters()['field']);
    }

    public function testValidateAddsErrorForBlankRequiredStringField(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow(['seller_name' => '   ']) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('seller_name', $error->getParameters()['field']);
        static::assertSame('The field "seller_name" must be a non-empty string.', $error->getParameters()['error']);
    }

    public function testValidateAddsJsonlValidationErrorForMalformedJsonl(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, "{\"item_id\": }\n", $errors);

        static::assertCount(1, $errors);

        $error = $errors->first();
        static::assertInstanceOf(JsonlValidationError::class, $error);
        static::assertSame(1, $error->getParameters()['line']);
    }

    public function testValidateDoesNotAddErrorsForValidOpenAiFeed(): void
    {
        $entity = $this->createProductExportEntity();

        $content = json_encode([
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'item_id' => 'SKU-1',
            'title' => 'Example',
            'description' => 'Example description',
            'url' => 'https://example.com/product',
            'brand' => 'ACME',
            'image_url' => 'https://example.com/image.jpg',
            'price' => '10.99 EUR',
            'availability' => 'in_stock',
            'group_id' => 'group-1',
            'listing_has_variations' => false,
            'seller_name' => 'Merchant',
            'seller_url' => 'https://example.com',
            'return_policy' => 'https://example.com/returns',
            'target_countries' => ['DE', 'FR'],
            'store_country' => 'DE',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(0, $errors);
    }

    public function testValidateAddsErrorsForInvalidOptionalAndDerivedFieldFormats(): void
    {
        $entity = $this->createProductExportEntity();

        $content = json_encode([
            'is_eligible_search' => 'yes',
            'is_eligible_checkout' => 'no',
            'item_id' => 'SKU-1',
            'title' => 'Example',
            'description' => 'Example description',
            'url' => 'relative/url',
            'brand' => 'ACME',
            'image_url' => 'not-a-url',
            'price' => 'EUR 10.99',
            'sale_price' => 'EUR 9.99',
            'availability' => 'unknown',
            'group_id' => 'group-1',
            'listing_has_variations' => 'false',
            'seller_name' => 'Merchant',
            'seller_url' => 'merchant.example',
            'return_policy' => 'merchant.example/returns',
            'target_countries' => ['DE', 'de'],
            'store_country' => 'de',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(12, $errors);
        static::assertSame(
            [
                'store_country',
                'url',
                'image_url',
                'seller_url',
                'return_policy',
                'price',
                'sale_price',
                'is_eligible_search',
                'is_eligible_checkout',
                'listing_has_variations',
                'availability',
                'target_countries',
            ],
            array_map(
                static fn (ProviderValidationError $error): string => (string) $error->getParameters()['field'],
                array_values(array_filter($errors->getElements(), static fn (mixed $error): bool => $error instanceof ProviderValidationError))
            )
        );
    }

    public function testValidateAddsErrorForInvalidTargetCountryCode(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow(['target_countries' => ['DE', 'de']]);

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('target_countries', $error->getParameters()['field']);
        static::assertSame('Each target country must be a 2-letter upper-case ISO country code.', $error->getParameters()['error']);
    }

    public function testValidateAddsErrorWhenTargetCountriesAreEmpty(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow(['target_countries' => []]) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('target_countries', $error->getParameters()['field']);
        static::assertSame('The field "target_countries" must be a non-empty array of ISO country codes.', $error->getParameters()['error']);
    }

    public function testValidateAddsErrorWhenAvailabilityDateIsMissingForPreOrder(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow(['availability' => 'pre_order']);

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('availability_date', $error->getParameters()['field']);
    }

    public function testValidateAddsErrorWhenAvailabilityDateIsInvalidForPreOrder(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow([
            'availability' => 'pre_order',
            'availability_date' => 'not-a-date',
        ]);

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('availability_date', $error->getParameters()['field']);
        static::assertSame('The field "availability_date" must be a valid date string.', $error->getParameters()['error']);
    }

    public function testValidateAllowsValidAvailabilityDateForPreOrder(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow([
            'availability' => 'pre_order',
            'availability_date' => '2026-03-23T10:15:00+00:00',
        ]);

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(0, $errors);
    }

    public function testValidateAddsErrorForDuplicateItemIds(): void
    {
        $entity = $this->createProductExportEntity();
        $content = $this->createValidRow() . \PHP_EOL . $this->createValidRow(['title' => 'Second row']) . \PHP_EOL;

        $errors = new ErrorCollection();

        (new OpenAiProductExportValidator(new JsonlRowParser()))->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('item_id', $error->getParameters()['field']);
        static::assertSame('The item_id "SKU-1" is not unique in the feed.', $error->getParameters()['error']);
    }

    private function createProductExportEntity(): ProductExportEntity
    {
        $entity = new ProductExportEntity();
        $entity->setId('test-export');
        $entity->setProvider('open-ai');
        $entity->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);

        return $entity;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createValidRow(array $overrides = []): string
    {
        return json_encode(array_replace([
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'item_id' => 'SKU-1',
            'title' => 'Example',
            'description' => 'Example description',
            'url' => 'https://example.com/product',
            'brand' => 'ACME',
            'image_url' => 'https://example.com/image.jpg',
            'price' => '10.99 EUR',
            'availability' => 'in_stock',
            'group_id' => 'group-1',
            'listing_has_variations' => false,
            'seller_name' => 'Merchant',
            'seller_url' => 'https://example.com',
            'return_policy' => 'https://example.com/returns',
            'target_countries' => ['DE', 'FR'],
            'store_country' => 'DE',
        ], $overrides), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
