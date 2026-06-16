<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\ProviderValidationError;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Validator\GoogleProductExportValidator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GoogleProductExportValidator::class)]
class GoogleProductExportValidatorTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateDoesNothingForOtherProviders(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $entity->setProvider('open-ai');

        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate($entity, 'not-xml', $errors);

        static::assertCount(0, $errors);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorWhenFileFormatIsNotXml(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $entity->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);

        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate($entity, '', $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('file_format', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForMalformedXml(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate($entity, '<not-xml', $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('xml', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForFeedWithoutItems(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateDoesNotAddErrorsForValidGoogleFeed(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        (new GoogleProductExportValidator())->validate(
            $entity,
            $this->wrapItems($this->createValidItem()),
            $errors
        );

        static::assertCount(0, $errors);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForMissingRequiredGoogleField(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['brand' => null]);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('brand', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidLink(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['link' => 'not-a-url']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('link', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidAvailability(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['availability' => 'unknown']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('availability', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidCondition(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['condition' => 'broken']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('condition', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidGender(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['gender' => 'Damen']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('gender', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAcceptsValidGender(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['gender' => 'female']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidSizeSystem(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['size_system' => 'EU-Größen']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('size_system', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAcceptsValidSizeSystem(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['size_system' => 'EU']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidAgeGroup(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['age_group' => 'Erwachsene']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('age_group', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAcceptsValidAgeGroup(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['age_group' => 'adult']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(0, $errors);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForInvalidPriceFormat(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['price' => 'EUR 10.99']);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('price', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorWhenIdentifiersAreMissingWithoutFlag(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $entity = $this->createProductExportEntity();
        $errors = new ErrorCollection();

        $item = $this->createValidItem(['gtin' => null, 'mpn' => null]);

        (new GoogleProductExportValidator())->validate($entity, $this->wrapItems($item), $errors);

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(ProviderValidationError::class, $error);
        static::assertSame('identifier_exists', $error->getParameters()['field']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAcceptsIdentifierExistsNo(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    public function testValidateAddsErrorForDuplicateIds(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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
