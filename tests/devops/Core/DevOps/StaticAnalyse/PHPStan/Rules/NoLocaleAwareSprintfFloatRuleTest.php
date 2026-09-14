<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoLocaleAwareSprintfFloatRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoLocaleAwareSprintfFloatRule>
 */
#[Package('framework')]
class NoLocaleAwareSprintfFloatRuleTest extends RuleTestCase
{
    private const ERROR = 'Do not use the locale-aware %f specifier in sprintf(). Use %F for locale-independent float serialization.';

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoLocaleAwareSprintfFloatRule/SprintfUsage.php'], [
            [self::ERROR, 10],
            [self::ERROR, 13],
            [self::ERROR, 14],
            [self::ERROR, 15],
            [self::ERROR, 16],
            [self::ERROR, 17],
            [self::ERROR, 20],
            [self::ERROR, 21],
        ]);
    }

    /**
     * @return NoLocaleAwareSprintfFloatRule
     */
    protected function getRule(): Rule
    {
        return new NoLocaleAwareSprintfFloatRule($this->createReflectionProvider());
    }
}
