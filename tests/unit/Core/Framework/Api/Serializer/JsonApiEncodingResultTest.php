<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncodingResult;
use Shopware\Core\Framework\Api\Serializer\Record;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonApiEncodingResult::class)]
class JsonApiEncodingResultTest extends TestCase
{
    public function testEmptyRelationshipDataDoesNotOverwriteExistingData(): void
    {
        $result = new JsonApiEncodingResult('/api');

        $firstRecord = new Record('entity-1', 'test_entity');
        $firstRecord->addRelationship('items', ['data' => [['type' => 'item', 'id' => 'item-1']]]);
        $firstRecord->addRelationship('categories', ['data' => [['type' => 'category', 'id' => 'cat-1']]]);

        $result->addIncluded($firstRecord);

        $secondRecord = new Record('entity-1', 'test_entity');
        // Empty array should NOT overwrite existing data
        $secondRecord->addRelationship('items', ['data' => []]);
        // Null should NOT overwrite existing data
        $secondRecord->addRelationship('categories', ['data' => null]);
        // New relationship with data should be added
        $secondRecord->addRelationship('tags', ['data' => [['type' => 'tag', 'id' => 'tag-1']]]);

        $result->addIncluded($secondRecord);

        $included = $result->getIncluded();
        $merged = array_values($included)[0];

        // Original data should be preserved
        static::assertSame([['type' => 'item', 'id' => 'item-1']], $merged->getRelationships()['items']['data']);
        static::assertSame([['type' => 'category', 'id' => 'cat-1']], $merged->getRelationships()['categories']['data']);
        // New relationship should be added
        static::assertSame([['type' => 'tag', 'id' => 'tag-1']], $merged->getRelationships()['tags']['data']);
    }

    /**
     * @param array<string, string> $firstLinks
     * @param array<string, string> $secondLinks
     * @param array<string, string> $expected
     */
    #[DataProvider('linkMergesProvider')]
    #[TestDox('carries the link of the second record over to the merged record: $_dataName')]
    public function testMergeCarriesOverLinksOfTheSecondRecord(array $firstLinks, array $secondLinks, array $expected): void
    {
        $result = new JsonApiEncodingResult('/api');

        $first = new Record('entity-1', 'test_entity');
        foreach ($firstLinks as $key => $value) {
            $first->addLink($key, $value);
        }
        $result->addIncluded($first);

        $second = new Record('entity-1', 'test_entity');
        foreach ($secondLinks as $key => $value) {
            $second->addLink($key, $value);
        }
        $result->addIncluded($second);

        $merged = array_values($result->getIncluded())[0];

        static::assertSame($expected, $merged->getLinks());
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>, array<string, string>}>
     */
    public static function linkMergesProvider(): iterable
    {
        yield 'incoming link is added' => [[], ['self' => '/api/entity/1'], ['self' => '/api/entity/1']];
        yield 'incoming link replaces existing link' => [['self' => '/api/old'], ['self' => '/api/new'], ['self' => '/api/new']];
        yield 'empty incoming link is not added' => [[], ['self' => ''], []];
        yield 'empty incoming link keeps existing link' => [['self' => '/api/old'], ['self' => ''], ['self' => '/api/old']];
        yield 'zero string incoming link is added' => [[], ['self' => '0'], ['self' => '0']];
        yield 'links of the first record other than the merged one survive' => [
            ['self' => '/api/old', 'related' => '/api/related'],
            ['self' => '/api/new'],
            ['self' => '/api/new', 'related' => '/api/related'],
        ];
    }

    /**
     * @param array<string, mixed> $firstAttributes
     * @param array<string, mixed> $secondAttributes
     * @param array<string, mixed> $expected
     */
    #[DataProvider('attributeMergesProvider')]
    #[TestDox('carries the attributes of the second record over to the merged record: $_dataName')]
    public function testMergeCarriesOverAttributesOfTheSecondRecord(array $firstAttributes, array $secondAttributes, array $expected): void
    {
        $result = new JsonApiEncodingResult('/api');

        $first = new Record('entity-1', 'test_entity');
        foreach ($firstAttributes as $key => $value) {
            $first->setAttribute($key, $value);
        }
        $result->addIncluded($first);

        $second = new Record('entity-1', 'test_entity');
        foreach ($secondAttributes as $key => $value) {
            $second->setAttribute($key, $value);
        }
        $result->addIncluded($second);

        $merged = array_values($result->getIncluded())[0];

        static::assertSame($expected, $merged->getAttributes());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, array<string, mixed>}>
     */
    public static function attributeMergesProvider(): iterable
    {
        yield 'null incoming attribute keeps existing value' => [['value' => 'existing'], ['value' => null], ['value' => 'existing']];
        yield 'empty array incoming attribute keeps existing value' => [['value' => 'existing'], ['value' => []], ['value' => 'existing']];
        yield 'string incoming attribute replaces existing value' => [['value' => 'existing'], ['value' => 'incoming'], ['value' => 'incoming']];
        yield 'zero string incoming attribute replaces existing value' => [['value' => 'existing'], ['value' => '0'], ['value' => '0']];
        yield 'zero integer incoming attribute replaces existing value' => [['value' => 'existing'], ['value' => 0], ['value' => 0]];
        yield 'zero float incoming attribute replaces existing value' => [['value' => 'existing'], ['value' => 0.0], ['value' => 0.0]];
        yield 'false incoming attribute replaces existing value' => [['value' => 'existing'], ['value' => false], ['value' => false]];
        yield 'empty string incoming attribute replaces existing value' => [['value' => 'existing'], ['value' => ''], ['value' => '']];
        yield 'attribute absent from the first record is added' => [
            ['value' => 'existing'],
            ['value' => 'incoming', 'extra' => 'added'],
            ['value' => 'incoming', 'extra' => 'added'],
        ];
    }

    public function testCircularReferenceHandling(): void
    {
        $result = new JsonApiEncodingResult('/api');

        // Simulate: User -> Media -> User (back-reference)
        // User is added to included first (via media.user path)
        $includedUser = new Record('user-1', 'user');
        $includedUser->setAttribute('name', 'John');
        $includedUser->addRelationship('media', ['data' => []]); // No media loaded on this traversal
        $result->addIncluded($includedUser);

        // Media is processed
        $media = new Record('media-1', 'media');
        $media->addRelationship('user', ['data' => ['type' => 'user', 'id' => 'user-1']]);
        $result->addIncluded($media);

        // Now original User (with media) is added to data
        $dataUser = new Record('user-1', 'user');
        $dataUser->setAttribute('name', 'John Doe');
        $dataUser->addRelationship('media', ['data' => [['type' => 'media', 'id' => 'media-1']]]);
        $result->addEntity($dataUser);

        // User should be in data (not included), with merged data
        static::assertTrue($result->containsInData('user-1', 'user'));
        static::assertFalse($result->containsInIncluded('user-1', 'user'));

        $data = $result->getData();
        $user = array_values($data)[0];

        // The media relationship should be preserved (not overwritten by empty array)
        static::assertSame([['type' => 'media', 'id' => 'media-1']], $user->getRelationships()['media']['data']);
    }
}
