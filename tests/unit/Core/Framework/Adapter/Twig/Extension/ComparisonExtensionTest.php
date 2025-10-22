<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Extension\ComparisonExtension;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(ComparisonExtension::class)]
class ComparisonExtensionTest extends TestCase
{
    public function testCompareNumericThrowsAnExceptionWhenOperatorIsUnsupported(): void
    {
        $this->expectExceptionObject(AdapterException::unsupportedOperator('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', 2, 0);
    }

    public function testCompareMixedThrowsAnExceptionWhenOperatorIsUnsupported(): void
    {
        $this->expectExceptionObject(AdapterException::unsupportedOperator('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', '5', 0);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareNumericThrowsAnExceptionWhenOperatorIsUnsupportedDeprecated(): void
    {
        $this->expectExceptionObject(new UnsupportedOperatorException('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', 2, 0);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareMixedThrowsAnExceptionWhenOperatorIsUnsupportedDeprecated(): void
    {
        $this->expectExceptionObject(AdapterException::unsupportedOperator('$', ComparisonExtension::class));
        $extension = new ComparisonExtension();

        $extension->compare('$', '5', 0);
    }
}
