<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpansionBudget;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingExpansionBudget::class)]
class InlineMappingExpansionBudgetTest extends TestCase
{
    public function testAllowsExactlyThePropertyLimitButRejectsTheNextByte(): void
    {
        $budget = new InlineMappingExpansionBudget();
        $budget->beginProperty();
        $budget->consume(InlineMappingExpansionBudget::MAX_PROPERTY_BYTES);

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $budget->consume(1);
    }

    public function testBeginningAnotherPropertyDoesNotRefundTheRenderBudget(): void
    {
        $budget = new InlineMappingExpansionBudget();
        for ($property = 0; $property < 4; ++$property) {
            $budget->beginProperty();
            $budget->consume(InlineMappingExpansionBudget::MAX_PROPERTY_BYTES);
        }

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(4194304, 'render'));

        $budget->beginProperty();
    }

    public function testRejectsAValueLargerThanTheRemainingRenderAllowance(): void
    {
        $budget = new InlineMappingExpansionBudget();
        for ($property = 0; $property < 4; ++$property) {
            $budget->beginProperty();
            $budget->consume(InlineMappingExpansionBudget::MAX_PROPERTY_BYTES - 1);
        }
        $budget->beginProperty();

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(4194304, 'render'));

        $budget->assertFits(5);
    }
}
