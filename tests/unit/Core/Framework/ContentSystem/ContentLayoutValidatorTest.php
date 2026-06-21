<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentLayoutValidator;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;

/**
 * @internal
 */
#[CoversClass(ContentLayoutValidator::class)]
class ContentLayoutValidatorTest extends TestCase
{
    #[TestDox('maps an intrinsic error to a constraint violation addressed by the element id')]
    public function testMapsIntrinsicErrorToConstraintViolation(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnregisteredComponent, 'bad-child', null, 'Component "Sw:Unknown" is not a registered element type.'),
        ]);

        $violations = $this->validatorReturning($report)->validate([]);

        static::assertCount(1, $violations);
        static::assertSame('bad-child', $violations->get(0)->getPropertyPath());
        static::assertSame(ViolationCode::UnregisteredComponent->value, $violations->get(0)->getCode());
    }

    #[TestDox('returns no violations when the diagnostics report is well-formed')]
    public function testReturnsNoViolationsWhenWellFormed(): void
    {
        $violations = $this->validatorReturning(new DiagnosticsReport([]))->validate([]);

        static::assertCount(0, $violations);
    }

    #[TestDox('filters out binding-scope errors from the validation result')]
    public function testBindingErrorsAreNotSurfaced(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'product', 'unresolved'),
        ]);

        $violations = $this->validatorReturning($report)->validate([]);

        static::assertCount(0, $violations);
    }

    private function validatorReturning(DiagnosticsReport $report): ContentLayoutValidator
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn(new LayoutAnalysis($report, []));

        return new ContentLayoutValidator($diagnostics);
    }
}
