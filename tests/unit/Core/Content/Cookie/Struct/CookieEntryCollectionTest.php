<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Framework\FrameworkException;

/**
 * @internal
 */
#[CoversClass(CookieEntryCollection::class)]
class CookieEntryCollectionTest extends TestCase
{
    public function testConstructorAndSet(): void
    {
        $cookieEntry = new CookieEntry('test.cookie');

        $collection = new CookieEntryCollection([$cookieEntry]);

        // Ensure that the cookie group is indexed by its snippet name
        static::assertSame($cookieEntry, $collection->get('test.cookie'));
    }

    public function testAdd(): void
    {
        $collection = new CookieEntryCollection();

        $cookieEntry = new CookieEntry('test.cookie');

        $collection->add($cookieEntry);

        // Ensure that the cookie group is indexed by its snippet name
        static::assertSame($cookieEntry, $collection->get('test.cookie'));
    }

    public function testExpectedClass(): void
    {
        $this->expectExceptionObject(FrameworkException::collectionElementInvalidType(CookieEntry::class, \stdClass::class));
        /** @phpstan-ignore argument.type (Pass wrong type on purpose to test validation) */
        (new CookieEntryCollection())->add(new \stdClass());
    }
}
