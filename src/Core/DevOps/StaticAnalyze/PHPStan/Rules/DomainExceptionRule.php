<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 *
 * @implements Rule<Throw_>
 */
#[Package('core')]
class DomainExceptionRule implements Rule
{
    use InTestClassTrait;

    public function getNodeType(): string
    {
        return Throw_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
            return [];
        }

        if ($scope->getClassReflection()->getName() === DecorationPatternException::class) {
            return [];
        }

        if (!$node instanceof Throw_) {
            return [];
        }

        if ($node->expr instanceof Node\Expr\StaticCall) {
            return $this->validateDomainExceptionClass($node->expr, $scope);
        }

        if (!$node->expr instanceof Node\Expr\New_) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/shopware/platform/blob/v6.4.20.0/adr/2022-02-24-domain-exceptions.md')->build(),
        ];
    }

    private function validateDomainExceptionClass(Node\Expr\StaticCall $node, Scope $scope): array
    {
        if (!is_subclass_of($node->class->toString(), HttpException::class)) {
            return [
                RuleErrorBuilder::message(\sprintf('Domain exception class %s has extend the \Shopware\Core\Framework\HttpException class', $node->class->toString()))->build(),
            ];
        }

        if (!\str_starts_with($node->class->toString(), 'Shopware\\Core\\')) {
            return [];
        }

        $parts = \explode('\\', $scope->getClassReflection()->getName());

        $expected = \sprintf('Shopware\\Core\\%s\\%s\\%sException', $parts[2], $parts[3], $parts[3]);

        if ($node->class->toString() !== $expected) {
            return [
                RuleErrorBuilder::message(\sprintf('Expected domain exception class %s, got %s', $expected, $node->class->toString()))->build(),
            ];
        }

        return [];
    }
}
