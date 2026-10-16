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
            // helpers declared in AssertingHelperTrait.php and AbstractHookCase.php, reported in those files
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertTrue'), 14],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 15],
            // the subscriber declared in AssertingSubscriberElsewhere.php, reported in that file
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertTrue'), 21],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 30],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertSame'), 31],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 37],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'fail'), 43],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 80],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 95],
            // helpers of the test class called from a hook, followed transitively and reported once
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertFalse'), 160],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 171],
            // NOT flagged: 54-55 (capture, assert after), 60 (a callable that is not a closure), 66 (on() of
            // another object), 112 (asserting on what a subscriber captured), 131-133 (a helper without
            // assertion), 154 (a listener on a plain dispatcher is another rule's business)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertionInEventHookRule(self::getContainer()->getService('defaultAnalysisParser'));
    }
}
