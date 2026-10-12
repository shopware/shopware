<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * PHPStan does not dispatch the class Name child of these expressions to Name rules.
 *
 * @implements Rule<Expr>
 *
 * @internal
 */
#[Package('framework')]
class NoClassAliasExpressionUsageRule implements Rule
{
    public function __construct(private readonly ClassAliasMap $classAliasMap)
    {
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $name = match (true) {
            $node instanceof New_,
            $node instanceof Instanceof_,
            $node instanceof StaticCall,
            $node instanceof StaticPropertyFetch => $node->class instanceof Name ? $node->class : null,
            default => null,
        };

        if ($name === null) {
            return [];
        }

        $className = $scope->resolveName($name);
        $canonicalClassName = $this->classAliasMap->canonicalClassName($className);
        if ($canonicalClassName === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Class alias "%s" is kept only for backwards compatibility. Use "%s" instead.',
                $className,
                $canonicalClassName
            ))
                ->identifier('shopware.classAliasUsage')
                ->line($name->getStartLine())
                ->build(),
        ];
    }
}
