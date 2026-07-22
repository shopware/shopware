<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\NoBCPlanningDeprecationRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoBCPlanningDeprecationRule>
 */
#[Package('framework')]
class NoBCPlanningDeprecationRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testBCPlanningReasonTagsAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoBCPlanningDeprecationRule/BCPlanningDeprecations.php'], [
            [
                'The deprecation reason "reason:becomes-final" is a BC-planning note, not a deprecation. Remove the deprecation annotation. Use the #[BecomesFinal] attribute instead.',
                8,
            ],
            [
                'The deprecation reason "reason:return-type-change" is a BC-planning note, not a deprecation. Remove the deprecation annotation. Use the #[ReturnTypeNarrowing] or #[ReturnTypeWidening] attribute instead.',
                13,
            ],
            [
                'The deprecation reason "reason:new-optional-parameter" is a BC-planning note, not a deprecation. Remove the deprecation annotation. Use the #[NewOptionalParameter] attribute instead.',
                21,
            ],
            [
                'The deprecation reason "reason:exception-change" is a BC-planning note, not a deprecation. Remove the deprecation annotation. Use the #[ExceptionChange] attribute instead.',
                35,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        // an empty pending list simulates the state after all annotations are migrated
        return new NoBCPlanningDeprecationRule([]);
    }
}
