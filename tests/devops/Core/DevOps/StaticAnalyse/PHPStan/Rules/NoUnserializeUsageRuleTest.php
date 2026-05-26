<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoUnserializeUsageRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoUnserializeUsageRule>
 */
#[Package('framework')]
class NoUnserializeUsageRuleTest extends RuleTestCase
{
    public function testRuleReportsProductionClass(): void
    {
        $class = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule\HasUnserialize';

        $this->analyse([__DIR__ . '/data/NoUnserializeUsageRule/HasUnserialize.php'], [
            [\sprintf(NoUnserializeUsageRule::ERROR_MESSAGE, $class), 9, NoUnserializeUsageRule::ERROR_TIP],
            [\sprintf(NoUnserializeUsageRule::ERROR_MESSAGE, $class), 10, NoUnserializeUsageRule::ERROR_TIP],
        ]);
    }

    public function testRuleReportsTestClassAsNonIgnorable(): void
    {
        $class = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule\HasUnserializeInTestClass';

        $this->analyse([__DIR__ . '/data/NoUnserializeUsageRule/HasUnserializeInTestClass.php'], [
            [\sprintf(NoUnserializeUsageRule::ERROR_MESSAGE, $class), 11, NoUnserializeUsageRule::ERROR_TIP_TESTS],
            ['No error with identifier shopware.unserializeUsage is reported on line 19.', 19],
            [\sprintf(NoUnserializeUsageRule::ERROR_MESSAGE, $class), 19, NoUnserializeUsageRule::ERROR_TIP_TESTS],
        ]);
    }

    public function testAllowlistedClassIsNotReported(): void
    {
        $this->analyse([
            __DIR__ . '/../../../../../../../src/Core/Test/Assert/Serialization.php',
        ], []);
    }

    /**
     * @return NoUnserializeUsageRule
     */
    protected function getRule(): Rule
    {
        return new NoUnserializeUsageRule($this->createReflectionProvider());
    }
}
