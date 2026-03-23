<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomField\CustomFieldXmlLoader;

/**
 * @internal
 */
#[CoversClass(CustomFieldXmlLoader::class)]
class CustomFieldXmlLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/custom-fields.xml');

        $sets = $customFields->getCustomFieldSets();
        static::assertCount(2, $sets);

        $firstSet = $sets[0];
        static::assertSame('test_set', $firstSet->getName());
        static::assertSame(['en-GB' => 'Test Set', 'de-DE' => 'Test-Set'], $firstSet->getLabel());
        static::assertSame(['product', 'customer'], $firstSet->getRelatedEntities());
        static::assertFalse($firstSet->getGlobal());
        static::assertCount(2, $firstSet->getFields());

        $intField = $firstSet->getFields()[0];
        static::assertSame('test_set_int_field', $intField->getName());
        static::assertSame(1, $intField->getPosition());

        $textField = $firstSet->getFields()[1];
        static::assertSame('test_set_text_field', $textField->getName());

        $secondSet = $sets[1];
        static::assertSame('test_global_set', $secondSet->getName());
        static::assertTrue($secondSet->getGlobal());
        static::assertSame(['order'], $secondSet->getRelatedEntities());
        static::assertCount(1, $secondSet->getFields());
    }

    public function testLoadInvalidFileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/nonexistent.xml');
    }

    public function testToEntityArrayWithoutAppId(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/custom-fields.xml');

        $set = $customFields->getCustomFieldSets()[0];
        $existingRelations = [];
        $existingFields = [];
        $result = $set->toEntityArray(null, $existingRelations, $existingFields);

        static::assertArrayNotHasKey('appId', $result);
        static::assertArrayHasKey('name', $result);
        static::assertSame('test_set', $result['name']);
        static::assertFalse($result['global']);
        static::assertCount(2, $result['relations']);
        static::assertCount(2, $result['customFields']);
    }

    public function testToEntityArrayWithAppId(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/custom-fields.xml');

        $set = $customFields->getCustomFieldSets()[0];
        $existingRelations = [];
        $existingFields = [];
        $result = $set->toEntityArray('some-app-id', $existingRelations, $existingFields);

        static::assertArrayHasKey('appId', $result);
        static::assertSame('some-app-id', $result['appId']);
        static::assertArrayHasKey('name', $result);
        static::assertSame('test_set', $result['name']);
    }
}
