<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\CoreExtension;

/**
 * @internal
 */
#[Package('framework')]
final class GuardedAttributeIgnoreExtension implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'getAttribute.notFound'
            || !$node instanceof StaticCall
            || !$node->class instanceof Node\Name\FullyQualified
            || $node->class->toString() !== CoreExtension::class
            || !$node->name instanceof Node\Identifier
            || $node->name->toLowerString() !== 'getattribute'
        ) {
            return false;
        }

        // Twig disables strict checks for intermediate lookups inside `is defined`, `??` and `default`.
        foreach ([6, 7] as $position) {
            $argument = $node->getArgs()[$position]->value ?? null;
            if ($argument instanceof ConstFetch && $argument->name->toLowerString() === 'true') {
                return true;
            }
        }

        return $scope->hasExpressionType($node)->yes();
    }
}
