<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\CustomField;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldSet;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CustomFieldSet::class)]
class CustomFieldSetTest extends TestCase
{
    public function testToEntityArrayForNewSetIncludesNameAndFieldNames(): void
    {
        $customFieldSet = $this->getCustomFieldSetFromManifest();

        $existingRelations = [];
        $existingFields = [];

        $payload = $customFieldSet->toEntityArray('app-id', $existingRelations, $existingFields);

        static::assertSame('app-id', $payload['appId']);
        static::assertArrayHasKey('name', $payload);
        static::assertSame('custom_field_test', $payload['name']);
        static::assertSame(['label' => $customFieldSet->getLabel(), 'translated' => true], $payload['config']);
        static::assertCount(2, $payload['relations']);
        static::assertSame('product', $payload['relations'][0]['entityName']);
        static::assertCount(2, $payload['customFields']);
        static::assertSame('bla_test', $payload['customFields'][0]['name']);
        static::assertSame([], $existingRelations);
        static::assertSame([], $existingFields);
    }

    public function testToEntityArrayUsesExistingIdentifiersWhenUpdating(): void
    {
        $customFieldSet = $this->getCustomFieldSetFromManifest();

        $existingRelationId = Uuid::randomHex();
        $existingFieldId = Uuid::randomHex();
        $existingSetId = Uuid::randomHex();

        $existingRelations = ['product' => $existingRelationId];
        $existingFields = ['bla_test' => $existingFieldId];

        $payload = $customFieldSet->toEntityArray('app-id', $existingRelations, $existingFields, $existingSetId);

        static::assertArrayHasKey('id', $payload);
        static::assertSame($existingSetId, $payload['id']);
        static::assertArrayNotHasKey('name', $payload);
        static::assertCount(2, $payload['relations']);
        static::assertSame($existingRelationId, $payload['relations'][0]['id']);
        static::assertArrayHasKey('customFields', $payload);
        static::assertSame($existingFieldId, $payload['customFields'][0]['id']);
        static::assertArrayNotHasKey('name', $payload['customFields'][0]);
        static::assertSame([], $existingRelations);
        static::assertSame([], $existingFields);
    }

    private function getCustomFieldSetFromManifest(): CustomFieldSet
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');
        $customFields = $manifest->getCustomFields();
        static::assertNotNull($customFields);
        $sets = $customFields->getCustomFieldSets();
        static::assertNotEmpty($sets);

        return $sets[0];
    }
}
