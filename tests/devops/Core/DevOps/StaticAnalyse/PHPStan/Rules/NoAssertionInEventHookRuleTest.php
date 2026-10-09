<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoAssertionInEventHookRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoAssertionInEventHookRule>
 */
#[Package('framework')]
class NoAssertionInEventHookRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoAssertionInEventHookRule/Cases.php'], [
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 25],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertSame'), 26],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 32],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'fail'), 38],
            // NOT flagged: 49-50 (capture, assert after), 55 (a callable that is not a closure), 61 (on() of
            // another object), 69 (a listener on a plain dispatcher is another rule's business)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertionInEventHookRule();
    }
}
