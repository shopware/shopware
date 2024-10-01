<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @implements Rule<FuncCall>
 *
 * @internal
 */
#[Package('core')]
class UseHasherRule implements Rule
{
    use InTestClassTrait;

    private const NOT_ALLOWED_FUNCTIONS = ['md5', 'md5_file', 'sha1', 'sha1_file', 'hash', 'hash_file'];
    private const HASHER_CLASS = Hasher::class;

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || $this->isInWebInstaller($scope)) {
            // if in a test namespace, don't care
            return [];
        }

        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Node\Name) {
            return [];
        }

        if ($scope->getClassReflection()?->getName() === self::HASHER_CLASS) {
            return [];
        }

        $name = $node->name->toString();

        if (\in_array($name, self::NOT_ALLOWED_FUNCTIONS, true)) {
            return [
                RuleErrorBuilder::message(\sprintf('Do not use %s function, use class %s instead.', $name, self::HASHER_CLASS))->build(),
            ];
        }

        return [];
    }

    /**
     * The webinstaller also runs on older installations and therefore we can't enforce the usage of the Hasher class.
     */
    protected function isInWebInstaller(Scope $scope): bool
    {
        if (!$scope->isInClass()) {
            return false;
        }

        $definitionClassReflection = $scope->getClassReflection()->getNativeReflection();

        $className = $definitionClassReflection->getName();

        return str_contains($className, 'Shopware\WebInstaller');
    }
}
