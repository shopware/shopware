<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AnalysisCompletionGuardTest extends TestCase
{
    public function testAllowsCompletedAnalysisWithDiagnostics(): void
    {
        AnalysisCompletionGuard::check([
            'status' => 'completed',
            'templates' => 2,
            'sourceCoverage' => 2,
            'failures' => [],
            'uncoveredSources' => [],
            'diagnostics' => 498,
        ]);

        $this->addToAssertionCount(1);
    }

    /**
     * @param array{status: string, templates: int, sourceCoverage: int, failures: list<string>, uncoveredSources: list<string>} $report
     */
    #[DataProvider('incompleteAnalyses')]
    public function testRejectsIncompleteAnalysis(array $report): void
    {
        $this->expectException(\RuntimeException::class);
        AnalysisCompletionGuard::check($report);
    }

    /**
     * @return iterable<string, array{array{status: string, templates: int, sourceCoverage: int, failures: list<string>, uncoveredSources: list<string>}}>
     */
    public static function incompleteAnalyses(): iterable
    {
        $complete = ['status' => 'completed', 'templates' => 2, 'sourceCoverage' => 2, 'failures' => [], 'uncoveredSources' => []];

        yield 'the analyzer failed' => [array_replace($complete, ['status' => 'failed'])];
        yield 'no templates were discovered' => [array_replace($complete, ['templates' => 0, 'sourceCoverage' => 0])];
        yield 'a template never reached analysis' => [array_replace($complete, ['sourceCoverage' => 1])];
        yield 'PHPStan reported an internal error' => [array_replace($complete, ['failures' => ['Internal error']])];
        yield 'the uncovered template list is nonempty' => [array_replace($complete, ['uncoveredSources' => ['unused.twig']])];
    }
}
