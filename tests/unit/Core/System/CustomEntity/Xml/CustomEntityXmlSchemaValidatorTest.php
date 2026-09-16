<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Entities;
use Shopware\Core\System\CustomEntity\Xml\Entity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomEntityXmlSchemaValidator::class)]
class CustomEntityXmlSchemaValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionIfEntitiesNotDefined(): void
    {
        $schema = new CustomEntityXmlSchema(__DIR__, null);

        $validator = new CustomEntityXmlSchemaValidator(new CustomEntityNameValidator());

        $this->expectExceptionObject(new \RuntimeException('No entities found in parsed xml file'));

        $validator->validate($schema);
    }

    /**
     * @param non-empty-string $xml
     */
    #[DataProvider('xmlProvider')]
    public function testValidate(string $xml, \Exception $expectedException): void
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        $entities = Entities::fromArray([
            'entities' => [Entity::fromXml($dom->documentElement)],
        ]);
        $schema = new CustomEntityXmlSchema(__DIR__, $entities);

        $validator = new CustomEntityXmlSchemaValidator(new CustomEntityNameValidator());

        $this->expectExceptionObject($expectedException);

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
     * @return \Generator<string, array{0: string, 1: \Exception}>
     */
    public static function xmlProvider(): \Generator
    {
        yield 'custom-fields-aware-but-no-label' => [
            <<<'XML'
            <entity name="ce_test" custom-fields-aware="true">
                <fields>
                    <string name="id"/>
                    <string name="name" translatable="true" />
                </fields>
            </entity>
            XML,
            CustomEntityException::noLabelProperty(),
        ];

        yield 'custom-fields-aware-non-existent-label-prop' => [
            <<<'XML'
            <entity name="ce_test" custom-fields-aware="true" label-property="label">
                <fields>
                    <string name="id"/>
                    <string name="name" translatable="true" />
                </fields>
            </entity>
            XML,
            CustomEntityException::labelPropertyNotDefined('label'),
        ];

        yield 'custom-fields-aware-non-string-label-prop' => [
            <<<'XML'
            <entity name="ce_test" custom-fields-aware="true" label-property="name">
                <fields>
                    <string name="id"/>
                    <int name="name" translatable="true" />
                </fields>
            </entity>
            XML,
            CustomEntityException::labelPropertyWrongType('name'),
        ];

        yield 'whitespace-in-field-name' => [
            <<<'XML'
            <entity name="ce_test">
                <fields>
                    <int name="foo bar" store-api-aware="true"/>
                </fields>
            </entity>
            XML,
            CustomEntityException::invalidFieldName('ce_test', 'foo bar'),
        ];

        yield 'backtick-in-entity-name' => [
            <<<'XML'
            <entity name="ce_test`x">
                <fields>
                    <string name="title" store-api-aware="true"/>
                </fields>
            </entity>
            XML,
            CustomEntityException::invalidEntityName('ce_test`x'),
        ];

        yield 'field-name-with-invalid-character' => [
            <<<'XML'
            <entity name="ce_test">
                <fields>
                    <string name="my-field" store-api-aware="true"/>
                </fields>
            </entity>
            XML,
            CustomEntityException::invalidFieldName('ce_test', 'my-field'),
        ];

        yield 'cascade-delete-to-core-table' => [
            <<<'XML'
            <entity name="ce_test">
                <fields>
                    <string name="id"/>
                    <one-to-many name="products" reference="product" on-delete="cascade"/>
                </fields>
            </entity>
            XML,
            new \RuntimeException('Cascade delete and referencing core tables are not allowed, field products'),
        ];

        yield 'reverse-required-to-core-table' => [
            <<<'XML'
            <entity name="ce_test">
                <fields>
                    <string name="id"/>
                    <one-to-many name="products" reference="product" on-delete="set-null" reverse-required="true" />
                </fields>
            </entity>
            XML,
            new \RuntimeException('Reverse required when referencing core tables is not allowed, field products'),
        ];
    }
}
