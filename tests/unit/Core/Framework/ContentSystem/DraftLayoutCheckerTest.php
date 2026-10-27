<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\DraftLayoutChecker;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DraftLayoutChecker::class)]
class DraftLayoutCheckerTest extends TestCase
{
    private const COMPONENT = 'Sw:Content:Text';

    #[TestDox('maps an intrinsic error to a constraint violation addressed by the element id')]
    public function testMapsIntrinsicErrorToConstraintViolation(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnregisteredComponent, 'bad-child', null, 'Component "Sw:Unknown" is not a registered element type.'),
        ]);

        $violations = $this->checkerReturning($report)->check([]);

        static::assertCount(1, $violations);
        static::assertSame('bad-child', $violations->get(0)->getPropertyPath());
        static::assertSame(ViolationCode::UnregisteredComponent->value, $violations->get(0)->getCode());
    }

    #[TestDox('returns no violations when the diagnostics report is well-formed')]
    public function testReturnsNoViolationsWhenWellFormed(): void
    {
        $violations = $this->checkerReturning(new DiagnosticsReport([]))->check([]);

        static::assertCount(0, $violations);
    }

    #[TestDox('filters out binding-scope errors from the validation result')]
    public function testBindingErrorsAreNotSurfaced(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'product', 'unresolved'),
        ]);

        $violations = $this->checkerReturning($report)->check([]);

        static::assertCount(0, $violations);
    }

    #[TestDox('allows a preview with an unavailable inline mapping')]
    public function testAllowsUnavailableInlineMapping(): void
    {
        $violations = $this->checker()->check([$this->elementWithToken('{{map:product.secret}}')]);

        static::assertCount(0, $violations);
    }

    private function elementWithToken(string $text): StoredElement
    {
        return new StoredElement(
            id: 'el-1',
            component: self::COMPONENT,
            properties: ['text' => StoredValue::ofString($text)],
        );
    }

    private function checkerReturning(DiagnosticsReport $report): DraftLayoutChecker
    {
        return $this->checker($report);
    }

    private function checker(?DiagnosticsReport $report = null): DraftLayoutChecker
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn(new LayoutAnalysis($report ?? new DiagnosticsReport([]), []));

        return new DraftLayoutChecker($diagnostics);
    }
}
