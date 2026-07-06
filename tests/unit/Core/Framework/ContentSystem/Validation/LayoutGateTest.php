<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(LayoutGate::class)]
class LayoutGateTest extends TestCase
{
    #[TestDox('analyses well-formedness with no bound source (null root context)')]
    public function testWellFormednessUsesNullRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $context = Context::createDefaultContext();

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())->method('analyze')
            ->with([], null, $context)
            ->willReturn(new LayoutAnalysis($report, []));

        $gate = new LayoutGate($diagnostics);

        static::assertSame($report, $gate->wellFormedness([], $context));
    }

    #[TestDox('analyses resolvability against the bound source root context')]
    public function testResolvabilityUsesProvidedRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $context = Context::createDefaultContext();

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())->method('analyze')
            ->with([], [], $context)
            ->willReturn(new LayoutAnalysis($report, []));

        $gate = new LayoutGate($diagnostics);

        static::assertSame($report, $gate->resolvability([], [], $context));
    }
}
