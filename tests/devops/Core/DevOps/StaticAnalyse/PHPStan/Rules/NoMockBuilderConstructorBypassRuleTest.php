<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoMockBuilderConstructorBypassRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoMockBuilderConstructorBypassRule>
 */
#[Package('framework')]
class NoMockBuilderConstructorBypassRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoMockBuilderConstructorBypassRule/Cases.php'], [
            // disableOriginalConstructor()->getMock() with no method restriction → redundant
            [NoMockBuilderConstructorBypassRule::ERROR_REDUNDANT, 24],
            // disableOriginalConstructor()->onlyMethods()->getMock() → partial-mock advisory
            [NoMockBuilderConstructorBypassRule::ERROR_PARTIAL, 30],
            // lines 36 (setConstructorArgs, no disable), 42 (onlyMethods, no disable), 48 (createMock) are NOT flagged
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoMockBuilderConstructorBypassRule();
    }
}
