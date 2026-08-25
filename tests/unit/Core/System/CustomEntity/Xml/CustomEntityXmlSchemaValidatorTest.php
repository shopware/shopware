<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Entities;
use Shopware\Core\System\CustomEntity\Xml\Entity;

/**
 * @internal
 */
#[CoversClass(CustomEntityXmlSchemaValidator::class)]
class CustomEntityXmlSchemaValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionIfEntitiesNotDefined(): void
    {
        $schema = new CustomEntityXmlSchema(__DIR__, null);

        $validator = new CustomEntityXmlSchemaValidator(new CustomEntityNameValidator());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No entities found in parsed xml file');

        $validator->validate($schema);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('xmlProvider')]
    public function testValidate(string $xml, string $exceptionClass, string $expectedMessage): void
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        $entities = Entities::fromArray([
            'entities' => [Entity::fromXml($dom->documentElement)],
        ]);
        $schema = new CustomEntityXmlSchema(__DIR__, $entities);

        $validator = new CustomEntityXmlSchemaValidator(new CustomEntityNameValidator());

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($expectedMessage);

        $validator->validate($schema);
    }

    #[DataProvider('validFieldNameProvider')]
    public function testValidateAllowsValidFieldNames(string $fieldName): void
    {
        $dom = new \DOMDocument();
        $dom->loadXML(\sprintf(
            '<entity name="ce_test"><fields><string name="%s" store-api-aware="true"/></fields></entity>',
            $fieldName
        ));

        \assert($dom->documentElement instanceof \DOMElement);

        $entities = Entities::fromArray([
            'entities' => [Entity::fromXml($dom->documentElement)],
        ]);

        static::expectNotToPerformAssertions();

        (new CustomEntityXmlSchemaValidator(new CustomEntityNameValidator()))->validate(new CustomEntityXmlSchema(__DIR__, $entities));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function validFieldNameProvider(): \Generator
    {
        yield 'snake_case' => ['top_seller_restrict'];
        yield 'camelCase' => ['topSeller'];
        yield 'leading underscore' => ['_internal'];
        yield 'with digits' => ['field2'];
        yield 'leading digit' => ['2fa_counter'];
        yield 'dollar sign' => ['price$usd'];
    }

    /**
     * @return array<string, array{0: string, 1: class-string<\Throwable>, 2: string}>
     */
    public static function xmlProvider(): array
    {
        return [
            'custom-fields-aware-but-no-label' => [
                <<<'XML'
                <entity name="ce_test" custom-fields-aware="true">
                    <fields>
                        <string name="id"/>
                        <string name="name" translatable="true" />
                    </fields>
                </entity>
                XML,
                CustomEntityException::class,
                'Entity must have a label property when it is custom field aware',
            ],
            'custom-fields-aware-non-existent-label-prop' => [
                <<<'XML'
                <entity name="ce_test" custom-fields-aware="true" label-property="label">
                    <fields>
                        <string name="id"/>
                        <string name="name" translatable="true" />
                    </fields>
                </entity>
                XML,
                CustomEntityException::class,
                'Entity label_property "label" is not defined in fields',
            ],
            'custom-fields-aware-non-string-label-prop' => [
                <<<'XML'
                <entity name="ce_test" custom-fields-aware="true" label-property="name">
                    <fields>
                        <string name="id"/>
                        <int name="name" translatable="true" />
                    </fields>
                </entity>
                XML,
                CustomEntityException::class,
                'Entity label_property "name" must be a string field',
            ],
            'whitespace-in-field-name' => [
                <<<'XML'
                <entity name="ce_test">
                    <fields>
                        <int name="foo bar" store-api-aware="true"/>
                    </fields>
                </entity>
                XML,
                CustomEntityException::class,
                'Field name "foo bar" of custom entity "ce_test" is invalid.',
            ],
            'backtick-in-entity-name' => [
                <<<'XML'
                <entity name="ce_test`x">
                    <fields>
                        <string name="title" store-api-aware="true"/>
                    </fields>
                </entity>
                XML,
                CustomEntityException::class,
                'Custom entity name "ce_test`x" is invalid.',
            ],
            'field-name-with-invalid-character' => [
                <<<'XML'
                <entity name="ce_test">
                    <fields>
                        <string name="my-field" store-api-aware="true"/>
                    </fields>
                </entity>
                XML,
                CustomEntityException::class,
                'Field name "my-field" of custom entity "ce_test" is invalid.',
            ],
            'cascade-delete-to-core-table' => [
                <<<'XML'
                <entity name="ce_test">
                    <fields>
                        <string name="id"/>
                        <one-to-many name="products" reference="product" on-delete="cascade"/>
                    </fields>
                </entity>
                XML,
                \RuntimeException::class,
                'Cascade delete and referencing core tables are not allowed, field products',
            ],
            'reverse-required-to-core-table' => [
                <<<'XML'
                <entity name="ce_test">
                    <fields>
                        <string name="id"/>
                        <one-to-many name="products" reference="product" on-delete="set-null" reverse-required="true" />
                    </fields>
                </entity>
                XML,
                \RuntimeException::class,
                'Reverse required when referencing core tables is not allowed, field products',
            ],
        ];
    }
}
