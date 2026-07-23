<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\InstanceOfExtension;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstanceOfExtension::class)]
class InstanceOfExtensionTest extends TestCase
{
    public function testMatchesAnObjectAgainstItsClass(): void
    {
        $extension = new InstanceOfExtension();

        static::assertTrue($extension->isInstanceOf(new \stdClass(), \stdClass::class));
    }

    public function testThrowsForNonObjectWhenV68IsActive(): void
    {
        $extension = new InstanceOfExtension();

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: Passing a non-object as $var is deprecated.'));

        // @phpstan-ignore argument.type (intentional legacy call)
        $extension->isInstanceOf('not-an-object', \stdClass::class);
    }

    public function testThrowsForNonStringClassWhenV68IsActive(): void
    {
        $extension = new InstanceOfExtension();

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: Passing a non-string as $class is deprecated.'));

        // @phpstan-ignore argument.type (intentional legacy call)
        $extension->isInstanceOf(new \stdClass(), new \stdClass());
    }
}
