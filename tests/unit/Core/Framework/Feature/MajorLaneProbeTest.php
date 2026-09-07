<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\MajorLaneProbe;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * Throwaway probe: the unit suite activates every registered flag, so with two majors in flight a
 * test only reaches the older major's behaviour by disabling the newer one. Do not merge with the
 * change.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(MajorLaneProbe::class)]
class MajorLaneProbeTest extends TestCase
{
    public function testTheUnitSuiteRunsTheNewestMajor(): void
    {
        static::assertSame('v6.9', (new MajorLaneProbe())->render());
    }

    #[DisabledFeatures(['v6.9.0.0'])]
    public function testDisabledFeaturesPinsATestToTheOlderMajor(): void
    {
        static::assertSame('legacy', (new MajorLaneProbe())->render());
    }
}
