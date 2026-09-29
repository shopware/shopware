<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
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
 * The rule keeps the assertion useful and version-stable by requiring each compared argument to be a list.
 * If you compare a value set regardless of order, wrap it in `array_values(...)`; if you compare keyed arrays
 * or objects, use `assertEquals()` instead (canonicalizing adds nothing there).
 *
 * An argument is accepted only when PHPStan can prove its type is a list (`isList()->yes()`, never `maybe()`).
 * This covers list literals and list-producing calls (`array_values()`/`array_keys()`/`range()`, two-arg
 * `array_column()`) automatically, and conservatively flags anything whose list-ness depends on a loose key-type
 * annotation (e.g. `array<string>` on `getIds()`) — so an inaccurate annotation can never mask a keyed array.
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class AssertEqualsCanonicalizingListArgumentRule implements Rule
{
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

            // Accept only when PHPStan can prove a list (yes(), never maybe()), so a loose array key-type
            // annotation can never turn a keyed array into a false negative. Covers list literals and
            // list-producing calls (array_values()/array_keys()/range(), two-arg array_column()) too.
            if ($scope->getType($args[$position]->value)->isList()->yes()) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $position + 1))
                ->identifier('shopware.assertEqualsCanonicalizingListArgument')
                ->build();
        }

        return $errors;
    }
}
