<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\CustomEntity\Xml\Field\Field;
use Shopware\Core\System\CustomEntity\Xml\Field\StringField;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Field::class)]
class FieldTest extends TestCase
{
    public function testFromXmlMergesAttributesAndChildElements(): void
    {
        $field = StringField::fromXml(self::element(<<<'XML'
        <string name="title" store-api-aware="true" translatable="true">
            <default>Hello</default>
        </string>
        XML));

        static::assertSame('title', $field->getName());
        static::assertTrue($field->isStoreApiAware());
        static::assertTrue($field->isTranslatable());
        static::assertFalse($field->isRequired());
        static::assertSame('Hello', $field->getDefault());
    }

    public function testFromXmlPrefersAttributesOverChildElements(): void
    {
        $field = StringField::fromXml(self::element(<<<'XML'
        <string name="title" store-api-aware="false">
            <name>ignored</name>
        </string>
        XML));

        static::assertSame('title', $field->getName());
        static::assertFalse($field->isStoreApiAware());
    }

    public function testJsonSerializeStripsExtensions(): void
    {
        $field = StringField::fromXml(self::element('<string name="title" store-api-aware="true"/>'));
        $field->addExtension('meta', new ArrayStruct(['foo' => 'bar']));

        $data = $field->jsonSerialize();

        static::assertArrayNotHasKey('extensions', $data);
        static::assertSame('title', $data['name']);
        static::assertSame('string', $data['type']);
        static::assertTrue($data['storeApiAware']);
    }

    /**
     * @param non-empty-string $xml
     */
    private static function element(string $xml): \DOMElement
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        return $dom->documentElement;
    }
}
