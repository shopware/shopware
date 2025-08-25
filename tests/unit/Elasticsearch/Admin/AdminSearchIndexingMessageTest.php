<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;

/**
 * @phpstan-type MessageData array{
 *     string,
 *     string,
 *     array<string, string>,
 *     array<string>
 * }
 *
 * @internal
 */
#[CoversClass(AdminSearchIndexingMessage::class)]
class AdminSearchIndexingMessageTest extends TestCase
{
    /**
     * @param MessageData $message1Data
     * @param MessageData $message2Data
     */
    #[DataProvider('provideDeduplicationData')]
    public function testDeduplicationId(array $message1Data, array $message2Data, bool $shouldBeEqual): void
    {
        $message1 = new AdminSearchIndexingMessage(...$message1Data);
        $message2 = new AdminSearchIndexingMessage(...$message2Data);

        $deduplicationId1 = $message1->deduplicationId();
        $deduplicationId2 = $message2->deduplicationId();

        static::assertIsString($deduplicationId1);
        static::assertIsString($deduplicationId2);
        static::assertLessThan(64, \strlen($deduplicationId1), 'Deduplication ID should be under 64 characters');

        if ($shouldBeEqual) {
            static::assertSame($deduplicationId1, $deduplicationId2);
        } else {
            static::assertNotSame($deduplicationId1, $deduplicationId2);
        }
    }

    /**
     * @return iterable<string, array{MessageData, MessageData, bool}>
     */
    public static function provideDeduplicationData(): iterable
    {
        yield 'same data' => [
            ['product', 'product-listing', ['sw-admin-product-listing' => 'sw-admin-product-listing_12345'], ['c1a28776116d4431a2208eb2960ec340']],
            ['product', 'product-listing', ['sw-admin-product-listing' => 'sw-admin-product-listing_12345'], ['c1a28776116d4431a2208eb2960ec340']],
            true,
        ];
        yield 'different order same data' => [
            ['product', 'product-listing', ['sw-admin-product-listing' => 'sw-admin-product-listing_12345', 'sw-admin-category-listing' => 'sw-admin-category-listing_67890'], ['c1a28776116d4431a2208eb2960ec340', 'c2b39887227e5542b3319fc4071fd451']],
            ['product', 'product-listing', ['sw-admin-category-listing' => 'sw-admin-category-listing_67890', 'sw-admin-product-listing' => 'sw-admin-product-listing_12345'], ['c2b39887227e5542b3319fc4071fd451', 'c1a28776116d4431a2208eb2960ec340']],
            true,
        ];
        yield 'different data' => [
            ['product', 'product-listing', ['sw-admin-product-listing' => 'sw-admin-product-listing_12345'], ['c1a28776116d4431a2208eb2960ec340']],
            ['product', 'product-listing', ['sw-admin-product-listing' => 'sw-admin-product-listing_12345'], ['c2b39887227e5542b3319fc4071fd451']],
            false,
        ];
    }

    public function testGetToRemoveIds(): void
    {
        $message = new AdminSearchIndexingMessage(
            'product',
            'product-listing',
            ['sw-admin-product-listing' => 'sw-admin-product-listing_12345'],
            ['c1a28776116d4431a2208eb2960ec340'],
            ['deadbeefdeadbeefdeadbeefdeadbeef']
        );

        static::assertSame(['deadbeefdeadbeefdeadbeefdeadbeef'], $message->getToRemoveIds());
    }
}
