<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('framework')]
class NoUnsafeRequestHasSessionRule implements Rule
{
    private const MESSAGE = 'Call Request::hasSession(true) instead of Request::hasSession(). Request::hasSession() itself does not initialize the lazy session, but it returns true for a lazy session factory. A later Request::getSession() initializes the session and can take the PHP session lock. Passive/read-only code, generic listeners, tracking, logging, background/admin-worker paths should use hasSession(true). Deliberate session-owning code may use Request::hasSession() only with a targeted @phpstan-ignore shopware.unsafeRequestHasSession comment that explains why initialization is intentional.';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'hasSession') {
            return [];
        }

        if (!(new ObjectType(Request::class))->isSuperTypeOf(TypeCombinator::removeNull($scope->getType($node->var)))->yes()) {
            return [];
        }

        $firstArgument = $node->getArgs()[0] ?? null;

        if ($firstArgument !== null && $scope->getType($firstArgument->value)->isTrue()->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('shopware.unsafeRequestHasSession')
                ->build(),
        ];
    }
}
