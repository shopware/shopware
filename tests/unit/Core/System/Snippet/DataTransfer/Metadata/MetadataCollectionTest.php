<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MetadataCollection::class)]
class MetadataCollectionTest extends TestCase
{
    public function testAddIfRequired(): void
    {
        $localCollection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T12:00:00+00:00',
                'progress' => 80,
            ]),
            MetadataEntry::create([
                'locale' => 'de-DE',
                'updatedAt' => '2024-01-02T12:00:00+00:00',
                'progress' => 90,
            ]),
        ]);

        static::assertCount(2, $localCollection);

        // Same timestamp, should not update
        $localCollection->addIfRequired(MetadataEntry::create([
            'locale' => 'en-GB',
            'updatedAt' => '2024-01-01T12:00:00+00:00',
            'progress' => 85,
        ]));

        static::assertCount(2, $localCollection);
        $gb = $localCollection->get('en-GB');

        static::assertInstanceOf(MetadataEntry::class, $gb);
        static::assertSame(80, $gb->progress);
        static::assertSame('en-GB', $gb->locale);
        static::assertFalse($gb->isUpdateRequired);
        $this->assertTimestamp('2024-01-01T12:00:00+00:00', $gb->updatedAt);

        // Newer timestamp, should update
        $localCollection->addIfRequired(MetadataEntry::create([
            'locale' => 'de-DE',
            'updatedAt' => '2024-01-03T12:00:00+00:00',
            'progress' => 95,
        ]));

        static::assertCount(2, $localCollection);
        $de = $localCollection->get('de-DE');

        static::assertInstanceOf(MetadataEntry::class, $de);
        static::assertSame(95, $de->progress);
        static::assertSame('de-DE', $de->locale);
        static::assertTrue($de->isUpdateRequired);
        $this->assertTimestamp('2024-01-03T12:00:00+00:00', $de->updatedAt);

        // New locale, should add
        $localCollection->addIfRequired(MetadataEntry::create([
            'locale' => 'fr-FR',
            'updatedAt' => '2024-01-04T12:00:00+00:00',
            'progress' => 70,
        ]));

        static::assertCount(3, $localCollection);
        $fr = $localCollection->get('fr-FR');

        static::assertInstanceOf(MetadataEntry::class, $fr);
        static::assertSame(70, $fr->progress);
        static::assertSame('fr-FR', $fr->locale);
        static::assertTrue($fr->isUpdateRequired);
        $this->assertTimestamp('2024-01-04T12:00:00+00:00', $fr->updatedAt);
    }

    public function testJsonSerialize(): void
    {
        $collection = new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T12:00:00.000+00:00',
                'progress' => 80,
            ]),
            MetadataEntry::create([
                'locale' => 'de-DE',
                'updatedAt' => '2024-01-02T12:00:00.000+00:00',
                'progress' => 90,
            ]),
        ]);

        $expected = [
            'en-GB' => [
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T12:00:00.000+00:00',
                'progress' => 80,
            ],
            'de-DE' => [
                'locale' => 'de-DE',
                'updatedAt' => '2024-01-02T12:00:00.000+00:00',
                'progress' => 90,
            ],
        ];

        static::assertSame($expected, $collection->jsonSerialize());
    }

    public function testGetLocalesRequiringUpdate(): void
    {
        $gb = MetadataEntry::create([
            'locale' => 'en-GB',
            'updatedAt' => '2024-01-01T12:00:00+00:00',
            'progress' => 80,
        ]);

        $de = MetadataEntry::create([
            'locale' => 'de-DE',
            'updatedAt' => '2024-01-02T12:00:00+00:00',
            'progress' => 90,
        ]);

        $de->markForUpdate();

        $collection = new MetadataCollection([$gb, $de]);

        $locales = $collection->getLocalesRequiringUpdate();
        static::assertCount(1, $locales);
        static::assertSame(['de-DE'], $locales);
    }

    public function testConstructorIndexesByLocales(): void
    {
        $elements = [
            MetadataEntry::create([
                'locale' => 'en-GB',
                'updatedAt' => '2024-01-01T12:00:00+00:00',
                'progress' => 80,
            ]),
            MetadataEntry::create([
                'locale' => 'de-DE',
                'updatedAt' => '2024-01-02T12:00:00+00:00',
                'progress' => 90,
            ]),
        ];

        $collection = new MetadataCollection($elements);
        $keys = array_keys($collection->getElements());
        static::assertCount(2, $keys);
        static::assertSame(['en-GB', 'de-DE'], $keys);
    }

    public function testAddIndexesByLocale(): void
    {
        $collection = new MetadataCollection();

        $entry = MetadataEntry::create([
            'locale' => 'en-GB',
            'updatedAt' => '2024-01-01T12:00:00+00:00',
            'progress' => 80,
        ]);

        $collection->add($entry);
        $keys = array_keys($collection->getElements());
        static::assertCount(1, $keys);
        static::assertSame(['en-GB'], $keys);
    }

    public function testSetIndexesByLocale(): void
    {
        $collection = new MetadataCollection();

        $entry = MetadataEntry::create([
            'locale' => 'en-GB',
            'updatedAt' => '2024-01-01T12:00:00+00:00',
            'progress' => 80,
        ]);

        $collection->set(null, $entry);
        $keys = array_keys($collection->getElements());
        static::assertCount(1, $keys);
        static::assertSame(['en-GB'], $keys);
    }

    private function assertTimestamp(string $expected, \DateTime $actual): void
    {
        static::assertSame(
            (new \DateTime($expected))->getTimestamp(),
            $actual->getTimestamp()
        );
    }
}
