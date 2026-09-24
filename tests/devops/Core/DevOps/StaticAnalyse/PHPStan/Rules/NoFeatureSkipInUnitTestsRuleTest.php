<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoFeatureSkipInUnitTestsRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoFeatureSkipInUnitTestsRule>
 */
#[Package('framework')]
class NoFeatureSkipInUnitTestsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoFeatureSkipInUnitTestsRule/Cases.php'], [
            [\sprintf(NoFeatureSkipInUnitTestsRule::ERROR_SKIP_GUARD, 'skipTestIfActive'), 18],
            [\sprintf(NoFeatureSkipInUnitTestsRule::ERROR_SKIP_GUARD, 'skipTestIfInActive'), 25],
            [NoFeatureSkipInUnitTestsRule::ERROR_IS_ACTIVE_GUARD, 32],
            [NoFeatureSkipInUnitTestsRule::ERROR_IS_ACTIVE_GUARD, 41],
            // NOT flagged: 49 (#[DisabledFeatures] is the intended tool), 56 (skip on an extension, no
            // flag involved), 65 (branching on the flag without skipping)
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/NoFeatureSkipInUnitTestsRule/CasesOutOfScope.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoFeatureSkipInUnitTestsRule(
            new Configuration(['featureSkipInUnitTestsEnabledNamespaces' => ['Shopware\\Tests\\Unit\\']]),
        );
    }
}
