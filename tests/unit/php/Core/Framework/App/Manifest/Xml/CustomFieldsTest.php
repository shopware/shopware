<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldSet;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Manifest\Xml\CustomFields
 */
class CustomFieldsTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test-manifest.xml');

        static::assertNotNull($manifest->getCustomFields());
        static::assertCount(1, $manifest->getCustomFields()->getCustomFieldSets());

        /** @var CustomFieldSet $customFieldSet */
        $customFieldSet = $manifest->getCustomFields()->getCustomFieldSets()[0];
        static::assertEquals('custom_field_test', $customFieldSet->getName());
        static::assertEquals([
            'en-GB' => 'Custom field test',
            'de-DE' => 'Zusatzfeld Test',
        ], $customFieldSet->getLabel());
        static::assertEquals(['product', 'customer'], $customFieldSet->getRelatedEntities());
        static::assertTrue($customFieldSet->getGlobal());

        static::assertCount(2, $customFieldSet->getFields());

        $fields = $customFieldSet->getFields();

        static::assertSame('bla_test', $fields[0]->getName());
        static::assertFalse($fields[0]->isAllowCustomerWrite());

        static::assertSame('bla_test2', $fields[1]->getName());
        static::assertTrue($fields[1]->isAllowCustomerWrite());
    }
}
