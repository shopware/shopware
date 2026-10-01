<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MetadataEntry::class)]
class MetadataEntryTest extends TestCase
{
    public function testMetadataEntry(): void
    {
        $data = [
            'locale' => 'en-GB',
            'updatedAt' => '2024-01-01T12:00:00+00:00',
            'progress' => 85,
        ];

        $metadataEntry = MetadataEntry::create($data);

        static::assertSame('en-GB', $metadataEntry->locale);
        static::assertEquals(new \DateTime('2024-01-01T12:00:00+00:00'), $metadataEntry->updatedAt);
        static::assertSame(85, $metadataEntry->progress);
        static::assertFalse($metadataEntry->isUpdateRequired);

        $metadataEntry->markForUpdate();
        static::assertTrue($metadataEntry->isUpdateRequired);
    }
}
