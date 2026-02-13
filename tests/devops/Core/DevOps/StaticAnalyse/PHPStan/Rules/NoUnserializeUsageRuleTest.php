<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoUnserializeUsageRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoUnserializeUsageRule>
 */
#[Package('framework')]
#[CoversClass(NoUnserializeUsageRule::class)]
class NoUnserializeUsageRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $class = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule\HasUnserialize';

        $message = \sprintf(NoUnserializeUsageRule::ERROR_MESSAGE, $class);
        $tip = NoUnserializeUsageRule::ERROR_TIP;

        $this->analyse([__DIR__ . '/data/NoUnserializeUsageRule/HasUnserialize.php'], [
            [$message, 9, $tip],
            [$message, 10, $tip],
        ]);
    }

    /**
     * @return NoUnserializeUsageRule
     */
    protected function getRule(): Rule
    {
        return new NoUnserializeUsageRule();
    }
}
