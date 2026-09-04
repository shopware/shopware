<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DiagnosticsReport::class)]
class DiagnosticsReportTest extends TestCase
{
    /**
     * @param list<Violation> $violations
     */
    #[DataProvider('derivesPredicatesProvider')]
    #[TestDox('derives predicates for $_dataName')]
    public function testDerivesPredicates(array $violations, bool $wellFormed, bool $resolvable): void
    {
        $report = new DiagnosticsReport($violations);

        static::assertSame($wellFormed, $report->isWellFormed());
        static::assertSame($resolvable, $report->isResolvable());
    }

    #[TestDox('collects an intrinsic error into the intrinsic bucket only')]
    public function testIntrinsicErrorBucket(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnregisteredComponent, 'el-1', null, 'unregistered'),
        ]);

        static::assertCount(1, $report->intrinsicErrors());
        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('collects a binding error into the binding bucket only')]
    public function testBindingErrorBucket(): void
    {
        $report = new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'product', 'unresolved'),
        ]);

        static::assertSame([], $report->intrinsicErrors());
        static::assertCount(1, $report->bindingErrors());
    }

    /**
     * @return iterable<string, array{violations: list<Violation>, wellFormed: bool, resolvable: bool}>
     */
    public static function derivesPredicatesProvider(): iterable
    {
        yield 'an intrinsic error' => [
            'violations' => [
                new Violation(ViolationCode::UnregisteredComponent, 'el-1', null, 'unregistered'),
            ],
            'wellFormed' => false,
            'resolvable' => true,
        ];

        yield 'a binding error' => [
            'violations' => [
                new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'product', 'unresolved'),
            ],
            'wellFormed' => true,
            'resolvable' => false,
        ];

        yield 'warnings only' => [
            'violations' => [
                new Violation(ViolationCode::OrphanedProvider, 'el-1', 'product', 'orphaned'),
                new Violation(ViolationCode::UnresolvedOptional, 'el-2', 'media', 'optional'),
            ],
            'wellFormed' => true,
            'resolvable' => true,
        ];
    }
}
