<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;

/**
 * @internal
 */
#[CoversClass(DiagnosticsReport::class)]
class DiagnosticsReportTest extends TestCase
{
    #[TestDox('isWellFormed is false and isResolvable true when only an intrinsic error is present')]
    public function testIntrinsicErrorBlocksWellFormedOnly(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnregisteredComponent, 'el-1', null, 'unregistered'),
        ]);

        static::assertFalse($report->isWellFormed());
        static::assertTrue($report->isResolvable());
        static::assertCount(1, $report->intrinsicErrors());
        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('isResolvable is false and isWellFormed true when only a binding error is present')]
    public function testBindingErrorBlocksResolvableOnly(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'product', 'unresolved'),
        ]);

        static::assertTrue($report->isWellFormed());
        static::assertFalse($report->isResolvable());
        static::assertSame([], $report->intrinsicErrors());
        static::assertCount(1, $report->bindingErrors());
    }

    #[TestDox('warnings never block either predicate')]
    public function testWarningsNeverBlock(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::OrphanedProvider, 'el-1', 'product', 'orphaned'),
            new Violation(ViolationCode::UnresolvedOptional, 'el-2', 'media', 'optional'),
        ]);

        static::assertTrue($report->isWellFormed());
        static::assertTrue($report->isResolvable());
    }
}
