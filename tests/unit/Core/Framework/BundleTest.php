<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
#[CoversClass(Bundle::class)]
class BundleTest extends TestCase
{
    public function testGetTwigComponentNamespace(): void
    {
        $bundleClass = new class extends Bundle {};

        static::assertSame(
            $bundleClass::getTwigComponentNamespace(),
            $bundleClass->getNamespace() . '\\Resources\\views\\components\\'
        );
    }
}
