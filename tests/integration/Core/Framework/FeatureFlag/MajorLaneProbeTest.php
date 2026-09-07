<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Throwaway probe: proves in CI that the integration job's `FEATURE_ALL` lane decides which majors
 * are active, and that the 6.8 lane does not see the 6.9 flags. Do not merge with the change.
 *
 * @internal
 */
#[Package('framework')]
class MajorLaneProbeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testTheLaneDecidesWhichMajorsAreActive(): void
    {
        $lane = (string) EnvironmentHelper::getVariable('FEATURE_ALL', '');
        $message = \sprintf('FEATURE_ALL=%s', $lane === '' ? '<empty>' : $lane);

        static::assertSame(\in_array($lane, ['v6.8.0.0', 'v6.9.0.0', 'major'], true), Feature::isActive('v6.8.0.0'), $message);
        static::assertSame(\in_array($lane, ['v6.9.0.0', 'major'], true), Feature::isActive('v6.9.0.0'), $message);
    }
}
