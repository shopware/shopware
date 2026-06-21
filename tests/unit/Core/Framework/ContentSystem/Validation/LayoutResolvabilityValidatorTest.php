<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutResolvabilityValidator;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(LayoutResolvabilityValidator::class)]
class LayoutResolvabilityValidatorTest extends TestCase
{
    #[TestDox('analyses well-formedness with no bound source (null root context)')]
    public function testWellFormednessUsesNullRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $context = Context::createDefaultContext();

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->method('analyze')
            ->with([], null, $context)
            ->willReturn(new LayoutAnalysis($report, []));

        $validator = new LayoutResolvabilityValidator($diagnostics);

        static::assertSame($report, $validator->wellFormedness([], $context));
    }

    #[TestDox('analyses resolvability against the bound source root context')]
    public function testResolvabilityUsesProvidedRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $context = Context::createDefaultContext();

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->method('analyze')
            ->with([], [], $context)
            ->willReturn(new LayoutAnalysis($report, []));

        $validator = new LayoutResolvabilityValidator($diagnostics);

        static::assertSame($report, $validator->resolvability([], [], $context));
    }
}
