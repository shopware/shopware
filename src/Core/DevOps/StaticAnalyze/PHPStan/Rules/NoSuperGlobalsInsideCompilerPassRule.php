<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * @internal
 *
 * @implements Rule<ArrayDimFetch>
 */
#[Package('core')]
class NoSuperGlobalsInsideCompilerPassRule implements Rule
{
    public function getNodeType(): string
    {
        return ArrayDimFetch::class;
    }

    /**
     * @param ArrayDimFetch $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();

        if ($class === null) {
            return [];
        }

        if (!$class->implementsInterface(CompilerPassInterface::class)) {
            return [];
        }

        if (!$node->var instanceof Variable) {
            return [];
        }

        if (!\in_array($node->var->name, ['_GET', '_POST', '_COOKIE', '_SERVER', '_FILES', '_REQUEST'], true)) {
            return [];
        }

        return ['Do not use super globals inside compiler passes.'];
    }
}
