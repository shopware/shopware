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
            // the subscriber declared in AssertingSubscriberElsewhere.php, reported in that file
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertTrue'), 21],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 28],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertSame'), 29],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 35],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'fail'), 41],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 78],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 93],
            // helpers of the test class called from a hook, followed transitively and reported once
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertFalse'), 144],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 155],
            // NOT flagged: 52-53 (capture, assert after), 58 (a callable that is not a closure), 64 (on() of
            // another object), 110 (asserting on what a subscriber captured), 129 (a helper without assertion),
            // 138 (a listener on a plain dispatcher is another rule's business)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertionInEventHookRule(self::getContainer()->getService('defaultAnalysisParser'));
    }
}
