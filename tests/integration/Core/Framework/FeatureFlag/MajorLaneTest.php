<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Feature\MajorLaneProbe;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Throwaway probe: proves in CI that the integration job's `FEATURE_ALL` lane is the only thing
 * deciding which majors are live, so the 6.8 lane never runs 6.9 code. Do not merge with the change.
 *
 * @internal
 */
#[Package('framework')]
class MajorLaneTest extends TestCase
{
    use KernelTestBehaviour;

    private bool $emitDeprecationsBackup;

    protected function setUp(): void
    {
        $this->emitDeprecationsBackup = Feature::$emitDeprecations;
        // The throw of a deprecated path is the assertion here, so do not depend on kernel.debug.
        Feature::$emitDeprecations = true;
    }

    protected function tearDown(): void
    {
        Feature::$emitDeprecations = $this->emitDeprecationsBackup;
    }

    public function testTheLaneDecidesWhichMajorsAreActive(): void
    {
        $lane = (string) EnvironmentHelper::getVariable('FEATURE_ALL', '');

        static::assertSame(\in_array($lane, ['v6.8.0.0', 'v6.9.0.0', 'major'], true), Feature::isActive('v6.8.0.0'), $this->lane());
        static::assertSame(\in_array($lane, ['v6.9.0.0', 'major'], true), Feature::isActive('v6.9.0.0'), $this->lane());
    }

    public function testTheLaneDecidesTheGatedBehaviour(): void
    {
        $expected = Feature::isActive('v6.9.0.0') ? 'v6.9' : 'legacy';

        static::assertSame($expected, (new MajorLaneProbe())->render(), $this->lane());
    }

    public function testTheDeprecatedPathThrowsOnlyInItsOwnLane(): void
    {
        $probe = new MajorLaneProbe();

        if (Feature::isActive('v6.9.0.0')) {
            $this->expectException(FeatureException::class);
            $probe->renderLegacy();

            return;
        }

        static::assertSame('legacy', $probe->renderLegacy(), $this->lane());
    }

    private function lane(): string
    {
        $lane = (string) EnvironmentHelper::getVariable('FEATURE_ALL', '');

        return \sprintf('FEATURE_ALL=%s', $lane === '' ? '<empty>' : $lane);
    }
}
