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
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addListener'), 21],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'removeListener'), 22],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addSubscriber'), 23],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'removeSubscriber'), 24],
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addListener'), 29],
            // a `@var EventDispatcher` annotation does not turn the container's dispatcher into one the test built
            [\sprintf(NoRuntimeListenerOnSharedEventDispatcherRule::ERROR, 'addListener'), 62],
            [NoRuntimeListenerOnSharedEventDispatcherRule::ERROR_HELPER, 72],
            // NOT flagged: 35-36 (a dispatcher the test built), 44 (a test double), 49-50 (no listener change),
            // 55 (not a dispatcher), 67 (a property natively typed as a dispatcher the test built), 77 (the helper
            // given a dispatcher the test built)
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
