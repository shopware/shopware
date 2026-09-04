<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutGate::class)]
class LayoutGateTest extends TestCase
{
    #[TestDox('hands the stored tree to the diagnostics pass unconverted, with no bound source (null root context)')]
    public function testWellFormednessPassesStoredTreeWithNullRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $tree = $this->tree();

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::identicalTo($tree), null)
            ->willReturn(new LayoutAnalysis($report, []));

        $gate = new LayoutGate($diagnostics);

        static::assertSame($report, $gate->wellFormedness($tree));
    }

    #[TestDox('hands the stored tree to the diagnostics pass unconverted, against the bound source root context')]
    public function testResolvabilityPassesStoredTreeWithProvidedRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $tree = $this->tree();
        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: StoredElement::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        )];

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::identicalTo($tree), static::identicalTo($rootContext))
            ->willReturn(new LayoutAnalysis($report, []));

        $gate = new LayoutGate($diagnostics);

        static::assertSame($report, $gate->resolvability($tree, $rootContext));
    }

    #[TestDox('hands an empty tree to the diagnostics pass unconverted')]
    public function testWellFormednessHandlesEmptyTree(): void
    {
        $report = new DiagnosticsReport([]);
        $tree = [];

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::identicalTo($tree), null)
            ->willReturn(new LayoutAnalysis($report, []));

        $gate = new LayoutGate($diagnostics);

        static::assertSame($report, $gate->wellFormedness($tree));
    }

    #[TestDox('hands an empty root context to the diagnostics pass unconverted')]
    public function testResolvabilityPassesEmptyRootContext(): void
    {
        $report = new DiagnosticsReport([]);
        $tree = $this->tree();
        $rootContext = [];

        $diagnostics = static::createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::identicalTo($tree), static::identicalTo($rootContext))
            ->willReturn(new LayoutAnalysis($report, []));

        $gate = new LayoutGate($diagnostics);

        static::assertSame($report, $gate->resolvability($tree, $rootContext));
    }

    /**
     * A non-empty tree, so the identity expectations above cannot pass vacuously on an empty array.
     *
     * @return list<StoredElement>
     */
    private function tree(): array
    {
        return [new StoredElement('el-1', 'Sw:Block')];
    }
}
