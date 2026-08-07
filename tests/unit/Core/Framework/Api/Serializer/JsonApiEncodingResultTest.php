<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
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
