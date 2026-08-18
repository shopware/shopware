<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoQueryInLoopRule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\QueryCallDetector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoQueryInLoopRule>
 */
#[Package('framework')]
class NoQueryInLoopRuleTest extends RuleTestCase
{
    public function testQueriesInLoopsAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoQueryInLoopRule/QueryInLoop.php'], [
            [
                'Connection::fetchAllKeyValue() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                39,
            ],
            [
                'QueryBuilder::fetchAllAssociative() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                64,
            ],
            [
                'EntityRepository::search() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                76,
            ],
            [
                'EntityRepository::update() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                86,
            ],
            [
                'EntityRepository::searchIds() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                100,
            ],
            [
                'Connection::fetchOne() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                113,
            ],
            [
                'Connection::fetchOne() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                124,
            ],
            [
                'Connection::fetchOne() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                144,
            ],
            [
                'Connection::fetchOne() is executed inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                155,
            ],
        ]);
    }

    public function testBatchedAndBoundedLoopsAreNotReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoQueryInLoopRule/ValidQueryUsage.php'], []);
    }

    public function testTestClassesAreNotReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoQueryInLoopRule/QueryInLoopInTestClass.php'], []);
    }

    public function testExcludedNamespacesAreNotReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoQueryInLoopRule/QueryInLoopInMigration.php'], []);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/data/NoQueryInLoopRule/extension.neon',
        ];
    }

    /**
     * @return NoQueryInLoopRule
     */
    protected function getRule(): Rule
    {
        return new NoQueryInLoopRule(new QueryCallDetector());
    }
}
