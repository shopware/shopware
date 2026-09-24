<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoFeatureSkipInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * Flag guards in a unit test never vary: every registered flag is active there.
 */
class Cases extends TestCase
{
    public function testSkipsForever(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        static::assertTrue(true);
    }

    public function testNeverSkips(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        static::assertTrue(true);
    }

    public function testSkipsForeverBehindIsActive(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('legacy branch only');
        }

        static::assertTrue(true);
    }

    public function testNeverSkipsBehindNegatedIsActive(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('needs the flag');
        }

        static::assertTrue(true);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyBranchThroughTheAttribute(): void
    {
        static::assertFalse(Feature::isActive('v6.8.0.0'));
    }

    public function testSkipOnSomethingElse(): void
    {
        if (!\extension_loaded('imagick')) {
            static::markTestSkipped('Imagick is not installed');
        }

        static::assertTrue(true);
    }

    public function testBranchingOnTheFlagWithoutSkipping(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::assertTrue(true);

            return;
        }

        static::assertFalse(false);
    }
}
