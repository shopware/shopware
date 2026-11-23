<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\FeatureFlagVersionRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<FeatureFlagVersionRule>
 */
#[Package('framework')]
class FeatureFlagVersionRuleTest extends RuleTestCase
{
    public function testMalformedVersionShapedFeatureFlagsAreRejected(): void
    {
        $this->analyse([__DIR__ . '/data/FeatureFlagVersionRule/feature-flags.php'], [
            ['Version-shaped feature flag "v6.8.0" must have four numeric parts (for example "v6.8.0.0").', 12],
            ['Version-shaped feature flag "v6.8.0" must have four numeric parts (for example "v6.8.0.0").', 13],
            ['Version-shaped feature flag "v6.7.0" must have four numeric parts (for example "v6.8.0.0").', 14],
            ['Version-shaped feature flag "v6.8.0" must have four numeric parts (for example "v6.8.0.0").', 15],
            ['Version-shaped feature flag "v6.9.0" must have four numeric parts (for example "v6.8.0.0").', 16],
            ['Version-shaped feature flag "V6_8_0" must have four numeric parts (for example "v6.8.0.0").', 17],
            ['Version-shaped feature flag "v6" must have four numeric parts (for example "v6.8.0.0").', 18],
            ['Version-shaped feature flag "v6.8" must have four numeric parts (for example "v6.8.0.0").', 19],
            ['Version-shaped feature flag "v6.8.0.0.1" must have four numeric parts (for example "v6.8.0.0").', 20],
            ['Version-shaped feature flag "v6.8.x.0" must have four numeric parts (for example "v6.8.0.0").', 21],
            ["Version-shaped feature flag \"v6.8.0.0\n\" must have four numeric parts (for example \"v6.8.0.0\").", 22],
        ]);
    }

    protected function getRule(): Rule
    {
        return new FeatureFlagVersionRule();
    }
}
