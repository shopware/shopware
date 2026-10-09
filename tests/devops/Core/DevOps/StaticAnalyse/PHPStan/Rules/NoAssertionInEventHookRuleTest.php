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
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 26],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertSame'), 27],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 33],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'fail'), 39],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertInstanceOf'), 76],
            [\sprintf(NoAssertionInEventHookRule::ERROR, 'assertNotNull'), 91],
            // NOT flagged: 50-51 (capture, assert after), 56 (a callable that is not a closure), 62 (on() of
            // another object), 108 (asserting on what a subscriber captured), 115 (a listener on a plain
            // dispatcher is another rule's business)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoAssertionInEventHookRule(self::getContainer()->getService('defaultAnalysisParser'));
    }
}
