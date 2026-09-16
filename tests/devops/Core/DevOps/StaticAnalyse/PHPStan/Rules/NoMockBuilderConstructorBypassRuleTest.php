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
            // plain $this->getMockBuilder()->getMock() → redundant
            [NoMockBuilderConstructorBypassRule::ERROR_REDUNDANT, 24],
            // static::getMockBuilder()->disableOriginalConstructor()->getMock() → redundant (StaticCall root)
            [NoMockBuilderConstructorBypassRule::ERROR_REDUNDANT, 30],
            // $this->getMockBuilder()->disableOriginalConstructor()->getMock() → redundant
            [NoMockBuilderConstructorBypassRule::ERROR_REDUNDANT, 36],
            // NOT flagged: 42 (partial), 48 (static partial), 54 (setConstructorArgs), 60 (partial), 66 (createMock)
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoMockBuilderConstructorBypassRule();
    }
}
