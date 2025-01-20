<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Package::class)]
class PackageTest extends TestCase
{
    public function testConstructor(): void
    {
        $package = new Package('framework');
        static::assertSame('framework', $package->package);
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
        static::assertSame('framework', Package::getPackageName(WithPackage::class));
    }

    public function testParentPackage(): void
    {
        static::assertSame('framework', Package::getPackageName(WithParentPackage::class, true));
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
#[Package('framework')]
class WithPackage
{
}

/**
 * @internal
 */
class WithParentPackage extends WithPackage
{
}
