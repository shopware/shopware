<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoDependsWithDataProviderRule;

/**
 * @internal
 *
 * @extends RuleTestCase<NoDependsWithDataProviderRule>
 */
class NoDependsWithDataProviderRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/NoDependsWithDataProvider/no-depends-with-data-provider-cases.php'],
            [
                [
                    'Test Shopware\Tests\Unit\Core\NoDependsWithDataProviderFixture\NoDependsWithDataProviderCases::testForbidden combines #[Depends] with #[DataProvider]. PHPUnit appends the dependency return value to the provided arguments, which breaks hard ("Cannot use positional argument after named argument during unpacking") as soon as a provider row uses named keys. Pass the dependency data via a static property set in the depended-upon test (or setUpBeforeClass) instead.',
                    19,
                ],
                [
                    'Test Shopware\Tests\Unit\Core\NoDependsWithDataProviderFixture\NoDependsWithDataProviderCases::testForbiddenReversed combines #[Depends] with #[DataProvider]. PHPUnit appends the dependency return value to the provided arguments, which breaks hard ("Cannot use positional argument after named argument during unpacking") as soon as a provider row uses named keys. Pass the dependency data via a static property set in the depended-upon test (or setUpBeforeClass) instead.',
                    26,
                ],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new NoDependsWithDataProviderRule();
    }
}
