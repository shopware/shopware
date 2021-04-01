<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\CustomFieldTypeNotFoundException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldTypeFactory;

class CustomFieldTypeFactoryTest extends TestCase
{
    public function testCreateFromXmlThrowsExceptionOnInvalidTag(): void
    {
        self::expectException(CustomFieldTypeNotFoundException::class);
        CustomFieldTypeFactory::createFromXml(new \DOMElement('invalid'));
    }

    public function testTranslatedForTag(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/custom-field-type-factory.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $field = $customFieldSet->getFields()[0];
        static::assertEquals('bool_field', $field->getName());
        static::assertEquals([
            'en-GB' => 'Test bool field',
            'de-DE' => 'Test bool field',
        ], $field->getLabel());
        static::assertEquals([
            'en-GB' => 'Help text',
            'de-DE' => 'Help text',
        ], $field->getHelpText());
    }
}
