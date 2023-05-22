<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
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

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {
    }

    public function getNodeType(): string
    {
        return Throw_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
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

        \assert($node->expr->class instanceof Node\Name);
        $exceptionClass = $node->expr->class->toString();

        if ($exceptionClass === DecorationPatternException::class) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/shopware/platform/blob/v6.4.20.0/adr/2022-02-24-domain-exceptions.md')->build(),
        ];
    }

    /**
     * @return list<RuleError>
     */
    private function validateDomainExceptionClass(Node\Expr\StaticCall $node, Scope $scope): array
    {
        \assert($node->class instanceof Node\Name);
        $exceptionClass = $node->class->toString();

        if (!\str_starts_with($exceptionClass, 'Shopware\\Core\\')) {
            return [];
        }

        $exception = $this->reflectionProvider->getClass($exceptionClass);
        if (!$exception->isSubclassOf(HttpException::class)) {
            return [
                RuleErrorBuilder::message(\sprintf('Domain exception class %s has extend the \Shopware\Core\Framework\HttpException class', $exceptionClass))->build(),
            ];
        }

        $reflection = $scope->getClassReflection();
        \assert($reflection !== null);
        if (!\str_starts_with($reflection->getName(), 'Shopware\\Core\\')) {
            return [];
        }
        $parts = \explode('\\', $reflection->getName());

        $expected = \sprintf('Shopware\\Core\\%s\\%s\\%sException', $parts[2], $parts[3], $parts[3]);

        if ($exceptionClass !== $expected) {
            return [
                RuleErrorBuilder::message(\sprintf('Expected domain exception class %s, got %s', $expected, $exceptionClass))->build(),
            ];
        }

        return [];
    }
}
