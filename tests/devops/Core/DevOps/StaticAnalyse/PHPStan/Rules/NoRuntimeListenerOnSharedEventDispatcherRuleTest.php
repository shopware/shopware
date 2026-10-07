<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoRuntimeListenerOnSharedEventDispatcherRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoRuntimeListenerOnSharedEventDispatcherRule>
 */
#[Package('framework')]
class NoRuntimeListenerOnSharedEventDispatcherRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoRuntimeListenerOnSharedEventDispatcherRule/Cases.php'], [
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addListener'), 18],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'removeListener'), 19],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addSubscriber'), 20],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'removeSubscriber'), 21],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addListener'), 26],
            // NOT flagged: 32-33 (a dispatcher the test built), 41 (a test double), 46-47 (no listener change),
            // 52 (not a dispatcher)
        ]);
    }

    public function testRuleDoesNotEnforceOutsideEnabledNamespaces(): void
    {
        $this->analyse([__DIR__ . '/data/NoRuntimeListenerOnSharedEventDispatcherRule/CasesOutOfScope.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoRuntimeListenerOnSharedEventDispatcherRule(
            new Configuration(['runtimeListenerOnSharedEventDispatcherEnabledNamespaces' => ['Shopware\\Tests\\Integration\\']]),
        );
    }
}
