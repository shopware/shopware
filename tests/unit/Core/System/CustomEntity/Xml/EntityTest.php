<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Entity;
use Shopware\Core\System\CustomEntity\Xml\Field\StringField;

/**
 * @internal
 */
#[CoversClass(Entity::class)]
class EntityTest extends TestCase
{
    public function testFromXml(): void
    {
        $xml = <<<'XML'
        <entity name="my_entity" custom-fields-aware="true" label-property="name">
            <fields>
                <string name="id"/>
                <string name="name" translatable="true" />
            </fields>
        </entity>
        XML;

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        $entity = Entity::fromXml($dom->documentElement);

        static::assertSame('my_entity', $entity->getName());
        static::assertTrue($entity->isCustomFieldsAware());
        static::assertSame('name', $entity->getLabelProperty());

        $fields = $entity->getFields();
        static::assertCount(2, $fields);

        static::assertInstanceOf(StringField::class, $fields[0]);
        static::assertEquals('id', $fields[0]->getName());
        static::assertInstanceOf(StringField::class, $fields[1]);
        static::assertEquals('name', $fields[1]->getName());
        static::assertTrue($fields[1]->isTranslatable());
    }

    public function testHasField(): void
    {
        $xml = <<<'XML'
        <entity name="my_entity" custom-fields-aware="true" label-property="name">
            <fields>
                <string name="id"/>
                <string name="name" translatable="true" />
            </fields>
        </entity>
        XML;

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        $entity = Entity::fromXml($dom->documentElement);

        static::assertTrue($entity->hasField('name'));
        static::assertFalse($entity->hasField('label'));
    }

    public function testGetField(): void
    {
        $xml = <<<'XML'
        <entity name="my_entity" custom-fields-aware="true" label-property="name">
            <fields>
                <string name="id"/>
                <string name="name" translatable="true" />
            </fields>
        </entity>
        XML;

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        \assert($dom->documentElement instanceof \DOMElement);

        $entity = Entity::fromXml($dom->documentElement);

        $field = $entity->getField('name');

        static::assertInstanceOf(StringField::class, $field);
        static::assertEquals('name', $field->getName());

        static::assertNull($entity->getField('label'));
    }
}
