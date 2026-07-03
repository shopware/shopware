<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\AssertEqualsCanonicalizingListArgumentRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<AssertEqualsCanonicalizingListArgumentRule>
 */
#[Package('framework')]
class AssertEqualsCanonicalizingListArgumentRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/AssertEqualsCanonicalizingListArgumentRule/Cases.php'], [
            // line 34: arg #2 is a bare variable
            [\sprintf(AssertEqualsCanonicalizingListArgumentRule::ERROR_MESSAGE, 2), 34],
            // line 36: arg #1 is a bare variable
            [\sprintf(AssertEqualsCanonicalizingListArgumentRule::ERROR_MESSAGE, 1), 36],
            // line 38: arg #2 is a keyed array literal
            [\sprintf(AssertEqualsCanonicalizingListArgumentRule::ERROR_MESSAGE, 2), 38],
        ]);
    }

    protected function getRule(): Rule
    {
        return new AssertEqualsCanonicalizingListArgumentRule();
    }
}
