<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Log\Package
 *
 * @package core
 */
class PackageTest extends TestCase
{
    public function testConstructor(): void
    {
        $package = new Package('test');
        static::assertSame('test', $package->package);
    }

    public function testNonExistingClass(): void
    {
        static::assertNull(Package::getPackageName('asdjkfljasdlkfjdas'));
    }

    public function testNoPackageAttribute(): void
    {
        static::assertNull(Package::getPackageName(NoPackage::class));
    }

    public function testPackage(): void
    {
        static::assertSame('test', Package::getPackageName(WithPackage::class));
    }
}

/**
 * @internal
 *
 * @package core
 */
class NoPackage
{
}

/**
 * @internal
 *
 * @package core
 */
#[Package('test')]
class WithPackage
{
}
