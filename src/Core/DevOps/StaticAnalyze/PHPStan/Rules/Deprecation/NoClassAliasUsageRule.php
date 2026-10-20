<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<Name>
 *
 * @internal
 */
#[Package('framework')]
class NoClassAliasUsageRule implements Rule
{
    public function __construct(private readonly ClassAliasMap $classAliasMap)
    {
    }

    public function getNodeType(): string
    {
        return Name::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->resolveName($node);
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
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
