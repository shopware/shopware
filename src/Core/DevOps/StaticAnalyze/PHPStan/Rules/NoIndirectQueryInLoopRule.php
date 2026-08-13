<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects the N+1 query problem one call away: a loop that calls a helper method of the same class which hits the
 * database, instead of holding the query itself. {@see NoQueryInLoopRule} only sees the query when it stands in the
 * loop body, so a loop like
 *
 *     foreach ($crossSellings as $crossSelling) {
 *         $elements->add($this->loadByIds($crossSelling, $context, $criteria));
 *     }
 *
 * stayed invisible although `loadByIds()` searches for products.
 *
 * The facts come from {@see QueryInLoopCollector}, because a rule that sees one call at a time cannot know what
 * another method does.
 *
 * @phpstan-import-type QueryInLoopFact from QueryInLoopCollector
 *
 * @implements Rule<CollectedDataNode>
 *
 * @internal
 */
#[Package('framework')]
class NoIndirectQueryInLoopRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @param CollectedDataNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        /** @var array<string, list<list<QueryInLoopFact>>> $collected */
        $collected = $node->get(QueryInLoopCollector::class);

        $querying = [];
        $delegations = [];
        $looped = [];

        foreach ($collected as $file => $factGroups) {
            foreach ($factGroups as $facts) {
                foreach ($facts as $fact) {
                    match ($fact['kind']) {
                        'query' => $querying[$fact['caller']] = true,
                        'call' => $delegations[$fact['caller']][$fact['target']] = true,
                        'looped' => $looped[] = ['file' => $file] + $fact,
                    };
                }
            }
        }

        $querying = $this->closeOverDelegations($querying, $delegations);

        $errors = [];
        $reported = [];

        foreach ($looped as $call) {
            if (!isset($querying[$call['target']])) {
                continue;
            }

            // A call that leads back to its own caller walks a tree or a nested structure - the recursion is the
            // shape of the data, not one query per record - so reporting it would bury the real findings.
            if ($this->leadsBackTo($call['caller'], $call['target'], $delegations)) {
                continue;
            }

            // the same call can be collected more than once, e.g. when a loop body is analysed repeatedly
            $key = $call['file'] . ':' . $call['line'];

            if (isset($reported[$key])) {
                continue;
            }

            $reported[$key] = true;

            $errors[] = RuleErrorBuilder::message(\sprintf(
                '%s() queries the database and is called inside a loop, which causes an N+1 query problem. Load the data for all iterations with a single query before the loop instead.',
                $call['method']
            ))
                ->file($call['file'])
                ->line($call['line'])
                ->identifier('shopware.indirectQueryInLoop')
                ->build();
        }

        return $errors;
    }

    /**
     * Whether the called method reaches its own caller again, directly or through other methods, which makes the call
     * part of a recursion instead of a step that repeats per record.
     *
     * @param array<string, array<string, true>> $delegations
     */
    private function leadsBackTo(string $caller, string $target, array $delegations): bool
    {
        $queue = [$target];
        $seen = [];

        while ($queue !== []) {
            $current = array_pop($queue);

            if ($current === $caller) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            foreach (array_keys($delegations[$current] ?? []) as $next) {
                $queue[] = $next;
            }
        }

        return false;
    }

    /**
     * A method also queries when it delegates to a method that queries, however many steps away that is.
     *
     * @param array<string, true> $querying
     * @param array<string, array<string, true>> $delegations
     *
     * @return array<string, true>
     */
    private function closeOverDelegations(array $querying, array $delegations): array
    {
        do {
            $grown = false;

            foreach ($delegations as $caller => $targets) {
                if (isset($querying[$caller])) {
                    continue;
                }

                foreach (array_keys($targets) as $target) {
                    if (!isset($querying[$target])) {
                        continue;
                    }

                    $querying[$caller] = true;
                    $grown = true;

                    break;
                }
            }
        } while ($grown);

        return $querying;
    }
}
