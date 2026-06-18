<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\ProviderValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Validator\GoogleProductExportValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GoogleProductExportValidator::class)]
class GoogleProductExportValidatorTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateDoesNothingForOtherProviders(): void
    {
        $entity = $this->createProductExportEntity();
        $entity->setProvider('open-ai');

        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate($entity, 'not-xml', $errors);

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorWhenFileFormatIsNotXml(): void
    {
        $entity = $this->createProductExportEntity();
        $entity->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);

        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate($entity, '', $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('file_format', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForMalformedXml(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate($entity, '<not-xml', $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('xml', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForFeedWithoutItems(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate(
            $entity,
            $this->wrapItems(''),
            $errors
        );

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('item', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateDoesNotAddErrorsForValidGoogleFeed(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate(
            $entity,
            $this->wrapItems($this->createValidItem()),
            $errors
        );

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForMissingRequiredGoogleField(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['brand' => null]);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('brand', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidLink(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['link' => 'not-a-url']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('link', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidAvailability(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['availability' => 'unknown']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('availability', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidCondition(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['condition' => 'broken']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('condition', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidGender(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['gender' => 'Damen']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('gender', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAcceptsValidGender(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['gender' => 'female']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidSizeSystem(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['size_system' => 'EU-Größen']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('size_system', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAcceptsValidSizeSystem(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['size_system' => 'EU']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidAgeGroup(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['age_group' => 'Erwachsene']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('age_group', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAcceptsValidAgeGroup(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['age_group' => 'adult']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForInvalidPriceFormat(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['price' => 'EUR 10.99']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('price', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorWhenIdentifiersAreMissingWithoutFlag(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['gtin' => null, 'mpn' => null]);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('identifier_exists', $error->getParameters()['field']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAcceptsIdentifierExistsNo(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem([
            'gtin' => null,
            'mpn' => null,
            'identifier_exists' => 'no',
        ]);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateAddsErrorForDuplicateIds(): void
    {
        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $content = $this->wrapItems($this->createValidItem() . $this->createValidItem(['title' => 'Second']));

        (new GoogleProductExportValidator())->validate($entity, $content, $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('id', $error->getParameters()['field']);
    }

    private function createProductExportEntity(): ProductExportEntity
    {
        $entity = new ProductExportEntity();
        $entity->setId('test-export');
        $entity->setProvider('google');
        $entity->setFileFormat(ProductExportEntity::FILE_FORMAT_XML);

        return $entity;
    }

    /**
     * @param array<string, string|null> $overrides
     */
    private function createValidItem(array $overrides = []): string
    {
        $defaults = [
            'id' => 'SKU-1',
            'title' => 'Example',
            'description' => 'Example description',
            'link' => 'https://example.com/product',
            'image_link' => 'https://example.com/image.jpg',
            'availability' => 'in_stock',
            'condition' => 'new',
            'price' => '10.99 EUR',
            'brand' => 'ACME',
            'gtin' => '0123456789012',
            'mpn' => 'MPN-1',
            'identifier_exists' => null,
        ];

        $values = array_replace($defaults, $overrides);

        $googleFields = ['id', 'image_link', 'availability', 'condition', 'price', 'brand', 'gtin', 'mpn', 'identifier_exists', 'gender', 'size_system', 'age_group'];

        $xml = '<item>';

        foreach ($values as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $tag = \in_array($field, $googleFields, true) ? 'g:' . $field : $field;
            $xml .= \sprintf('<%s>%s</%s>', $tag, htmlspecialchars((string) $value, \ENT_XML1), $tag);
        }

        $xml .= '</item>';

        return $xml;
    }

    private function wrapItems(string $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8" ?>'
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">'
            . '<channel>'
            . $items
            . '</channel>'
            . '</rss>';
    }
}
