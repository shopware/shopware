<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Symplify\NoReturnSetterMethodWithFluentSettersRule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Symplify\NoReturnSetterMethodWithFluentSettersRule;
use Symplify\PHPStanRules\Rules\NoReturnSetterMethodRule;

/**
 * Rule decorates symplify rule, so we copy the test case and adjust it, see https://github.com/symplify/phpstan-rules/pull/39
 *
 * @extends RuleTestCase<NoReturnSetterMethodWithFluentSettersRule>
 *
 * @internal
 */
#[CoversClass(NoReturnSetterMethodWithFluentSettersRule::class)]
class NoReturnSetterMethodWithFluentSettersRuleTest extends RuleTestCase
{
    /**
     * @param list<array{0: string, 1: int, 2?: string}> $expectedErrorsWithLines
     */
    #[DataProvider('provideData')]
    #[RunInSeparateProcess]
    public function testRule(string $filePath, array $expectedErrorsWithLines): void
    {
        $this->analyse([$filePath], $expectedErrorsWithLines);
    }

    /**
     * @return \Iterator<array{string, list<array{0: string, 1: int, 2?: string}>}>
     */
    public static function provideData(): \Iterator
    {
        yield [__DIR__ . '/../../data/NoReturnSetterMethod/SomeSetterClass.php', [
            [NoReturnSetterMethodRule::ERROR_MESSAGE, 12],
            [NoReturnSetterMethodRule::ERROR_MESSAGE, 17],
            [NoReturnSetterMethodRule::ERROR_MESSAGE, 22],
        ]];

        yield [__DIR__ . '/../../data/NoReturnSetterMethod/FluentSetterClass.php', []];

        yield [__DIR__ . '/../../data/NoReturnSetterMethod/SkipEmptyReturn.php', []];
        yield [__DIR__ . '/../../data/NoReturnSetterMethod/SkipVoidSetter.php', []];
        yield [__DIR__ . '/../../data/NoReturnSetterMethod/SkipSetUp.php', []];
        yield [__DIR__ . '/../../data/NoReturnSetterMethod/SkipArrayFilter.php', []];
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/configured_rule.neon'];
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(NoReturnSetterMethodWithFluentSettersRule::class);
    }
}
