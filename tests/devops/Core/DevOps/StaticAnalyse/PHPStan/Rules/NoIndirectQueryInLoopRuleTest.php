<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoIndirectQueryInLoopRule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\QueryCallDetector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\QueryInLoopCollector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoIndirectQueryInLoopRule>
 */
#[Package('framework')]
class NoIndirectQueryInLoopRuleTest extends RuleTestCase
{
    public function testHelpersThatQueryAreReportedAtTheCallInTheLoop(): void
    {
        $this->analyse([__DIR__ . '/data/NoIndirectQueryInLoopRule/IndirectQueryInLoop.php'], [
            [
                'loadOne() queries the database and is called inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                28,
            ],
            [
                'delegate() queries the database and is called inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                46,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/data/NoIndirectQueryInLoopRule/extension.neon',
        ];
    }

    /**
     * @return NoIndirectQueryInLoopRule
     */
    protected function getRule(): Rule
    {
        return new NoIndirectQueryInLoopRule();
    }

    /**
     * @return list<Collector<Node, mixed>>
     */
    protected function getCollectors(): array
    {
        return [new QueryInLoopCollector(new QueryCallDetector())];
    }
}
