<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('framework')]
class NoFilesystemExistsRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'exists') {
            return [];
        }

        $calledOnType = $scope->getType($node->var);

        if (!$calledOnType->isObject()->yes()) {
            return [];
        }

        $classNames = $calledOnType->getObjectClassNames();
        if (!\in_array(Filesystem::class, $classNames, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('Avoid using exists of %s which uses file_exists. %s', Filesystem::class, NoFileExistsRule::FILE_EXISTS_INFORMATION))
                ->identifier('shopware.fileExists')
                ->build(),
        ];
    }
}
