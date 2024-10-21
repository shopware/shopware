<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Package::class)]
class PackageTest extends TestCase
{
    public function testConstructor(): void
    {
        $package = new Package('core');
        static::assertSame('core', $package->package);
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
        static::assertSame('core', Package::getPackageName(WithPackage::class));
    }

    public function testParentPackage(): void
    {
        static::assertSame('core', Package::getPackageName(WithParentPackage::class, true));
    }

    public function testParentPackageWithoutFlag(): void
    {
        static::assertNull(Package::getPackageName(WithParentPackage::class));
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
#[Package('core')]
class WithPackage
{
}

/**
 * @internal
 */
class WithParentPackage extends WithPackage
{
}
