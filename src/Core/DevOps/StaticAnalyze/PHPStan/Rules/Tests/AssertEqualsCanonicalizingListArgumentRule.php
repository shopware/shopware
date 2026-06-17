<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Requires the two compared arguments of `assertEqualsCanonicalizing()` to be lists.
 *
 * `assertEqualsCanonicalizing()` only canonicalizes order-insensitively (value-sort) when BOTH arguments are
 * lists. For keyed arrays it falls back to a key-sensitive `ksort`, and for objects it does nothing — and that
 * split changed in PHPUnit 12 (sebastian/comparator 8.x: `usort` when `array_is_list()` on both, else `ksort`).
 * So `assertEqualsCanonicalizing([$a, $b], $collection->getIds())` (where `getIds()` is keyed by id) silently
 * compares list-keys `0,1` against id-keys and fails, even though the values match.
 *
 * The rule keeps the assertion useful and version-stable by requiring each compared argument to be a list:
 * a list array-literal or a list-producing call (`array_values()`/`array_keys()`/`array_column()`/`range()`).
 * If you compare a value set regardless of order, wrap it in `array_values(...)`; if you compare keyed arrays
 * or objects, use `assertEquals()` instead (canonicalizing adds nothing there).
 *
 * Detection is structural — it inspects the argument expression, not its inferred type — so it does not depend
 * on the accuracy of array key-type annotations (e.g. a loose `array<string>` on `getIds()`).
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class AssertEqualsCanonicalizingListArgumentRule implements Rule
{
    /**
     * @var list<string>
     */
    private const LIST_PRODUCING_FUNCTIONS = ['array_values', 'array_keys', 'array_column', 'range'];

    public const ERROR_MESSAGE = 'assertEqualsCanonicalizing() argument #%d must be a list so the comparison value-sorts: use a list literal or array_values()/array_keys()/array_column(). For keyed arrays or objects use assertEquals() instead — canonicalizing only affects lists.';

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->name !== 'assertEqualsCanonicalizing') {
            return [];
        }

        $args = $node->getArgs();

        $errors = [];
        foreach ([0, 1] as $position) {
            if (!isset($args[$position])) {
                continue;
            }

            if ($this->isList($args[$position]->value)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $position + 1))
                ->identifier('shopware.assertEqualsCanonicalizingListArgument')
                ->build();
        }

        return $errors;
    }

    private function isList(Node\Expr $value): bool
    {
        if ($value instanceof Array_) {
            // A list literal has no explicit keys (e.g. [$a, $b]); any `key => value` item makes it non-list here.
            foreach ($value->items as $item) {
                if ($item->key !== null) {
                    return false;
                }
            }

            return true;
        }

        if ($value instanceof FuncCall && $value->name instanceof Name) {
            return \in_array($value->name->toLowerString(), self::LIST_PRODUCING_FUNCTIONS, true);
        }

        return false;
    }
}
