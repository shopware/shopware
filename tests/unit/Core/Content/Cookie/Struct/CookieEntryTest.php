<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieEntry::class)]
class CookieEntryTest extends TestCase
{
    public function testDefaults(): void
    {
        $entry = new CookieEntry();
        static::assertFalse($entry->isHidden());
        static::assertFalse($entry->hidden); // Test public property
    }

    public function testConstructorWithHiddenTrue(): void
    {
        $entry = new CookieEntry(hidden: true);
        static::assertTrue($entry->isHidden());
        static::assertTrue($entry->hidden); // Test public property
    }

    public function testConstructorWithHiddenFalse(): void
    {
        $entry = new CookieEntry(hidden: false);
        static::assertFalse($entry->isHidden());
        static::assertFalse($entry->hidden); // Test public property
    }

    public function testSetHidden(): void
    {
        $entry = new CookieEntry();
        static::assertFalse($entry->isHidden());

        $entry->setHidden(true);
        static::assertTrue($entry->isHidden());
        static::assertTrue($entry->hidden); // Test public property

        $entry->setHidden(false);
        static::assertFalse($entry->isHidden());
        static::assertFalse($entry->hidden); // Test public property
    }

    public function testIsHidden(): void
    {
        $entry = new CookieEntry(hidden: true);
        static::assertTrue($entry->isHidden());

        $entry = new CookieEntry(hidden: false);
        static::assertFalse($entry->isHidden());
    }

    public function testGetApiAlias(): void
    {
        $entry = new CookieEntry();
        static::assertSame('cookie_entry', $entry->getApiAlias());
    }
}
