<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Log\Package
 */
class PackageTest extends TestCase
{
    public function setUp(): void
    {
        if (version_compare(\PHP_VERSION, '8', '<')) {
            static::markTestSkipped('Needs php 8');
        }
    }

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
 */
class NoPackage
{
}

/**
 * @internal
 */
#[Package('test')]
class WithPackage
{
}
