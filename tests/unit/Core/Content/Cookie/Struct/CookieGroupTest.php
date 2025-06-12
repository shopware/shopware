<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieGroup::class)]
class CookieGroupTest extends TestCase
{
    public function testDefaults(): void
    {
        $group = new CookieGroup(isRequired: false);
        static::assertFalse($group->isRequired); // Test public property
        static::assertEmpty($group->entries); // Test public property
    }

    public function testConstructorWithRequiredTrue(): void
    {
        $group = new CookieGroup(isRequired: true);
        static::assertTrue($group->isRequired); // Test public property
    }

    public function testConstructorWithRequiredFalse(): void
    {
        $group = new CookieGroup(isRequired: false);
        static::assertFalse($group->isRequired); // Test public property
    }

    public function testConstructorWithEntries(): void
    {
        $entry1 = new CookieEntry();
        $entry2 = new CookieEntry();
        $entries = [$entry1, $entry2];

        $group = new CookieGroup(isRequired: false, entries: $entries);
        static::assertSame($entries, $group->entries); // Test public property
    }

    public function testPublicPropertyModification(): void
    {
        $group = new CookieGroup(isRequired: false);
        static::assertFalse($group->isRequired);

        $group->isRequired = true;
        static::assertTrue($group->isRequired);

        $group->isRequired = false;
        static::assertFalse($group->isRequired);
    }

    public function testSetEntries(): void
    {
        $group = new CookieGroup(isRequired: false);
        static::assertEmpty($group->entries);

        $entry1 = new CookieEntry();
        $entry2 = new CookieEntry();
        $entries = [$entry1, $entry2];

        // Set entries through the public property
        $group->entries = $entries;
        static::assertSame($entries, $group->entries); // Test public property

        $group->entries = [];
        static::assertEmpty($group->entries); // Test public property
    }

    public function testGetEntries(): void
    {
        $entry1 = new CookieEntry();
        $entry2 = new CookieEntry();
        $entries = [$entry1, $entry2];

        $group = new CookieGroup(isRequired: false, entries: $entries);
        static::assertSame($entries, $group->entries);

        $group = new CookieGroup(isRequired: false, entries: []);
        static::assertEmpty($group->entries);
    }

    public function testGetApiAlias(): void
    {
        $group = new CookieGroup(isRequired: false);
        static::assertSame('cookie_group', $group->getApiAlias());
    }
}
