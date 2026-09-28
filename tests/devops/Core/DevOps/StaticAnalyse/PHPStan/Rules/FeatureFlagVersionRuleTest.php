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
    public function testThreePartFeatureFlagsAreRejected(): void
    {
        $this->analyse([__DIR__ . '/data/FeatureFlagVersionRule/feature-flags.php'], [
            ['Feature flag "v6.8.0" uses a three-part version. Use "v6.8.0.0" instead.', 12],
            ['Feature flag "v6.8.0" uses a three-part version. Use "v6.8.0.0" instead.', 13],
            ['Feature flag "v6.7.0" uses a three-part version. Use "v6.7.0.0" instead.', 14],
            ['Feature flag "v6.8.0" uses a three-part version. Use "v6.8.0.0" instead.', 15],
            ['Feature flag "v6.9.0" uses a three-part version. Use "v6.9.0.0" instead.', 16],
            ['Feature flag "V6_8_0" uses a three-part version. Use "v6.8.0.0" instead.', 17],
        ]);
    }

    protected function getRule(): Rule
    {
        return new FeatureFlagVersionRule();
    }
}
