<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class AnalysisCompletionGuard
{
    /**
     * @param array{status: string, templates: int, sourceCoverage: int, failures: list<string>, uncoveredSources: list<string>} $report
     */
    public static function check(array $report): void
    {
        if ($report['status'] !== 'completed'
            || $report['templates'] === 0
            || $report['sourceCoverage'] !== $report['templates']
            || $report['failures'] !== []
            || $report['uncoveredSources'] !== []
        ) {
            throw new \RuntimeException('TwigStan did not analyse every Storefront template. See the coverage report and analysis log.');
        }
    }
}
