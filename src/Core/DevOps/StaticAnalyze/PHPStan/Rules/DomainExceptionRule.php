<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\RedisReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyException;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;

/**
 * @internal
 *
 * @implements Rule<Throw_>
 */
#[Package('core')]
class DomainExceptionRule implements Rule
{
    use InTestClassTrait;

    private const VALID_SUB_DOMAINS = [
        'Cart',
        'Payment',
        'Order',
    ];

    /**
     * @var array<string, string>
     */
    private const REMAPPED_DOMAINS = [
        Kernel::class => FrameworkException::class,
        Framework::class => FrameworkException::class,
        VarnishReverseProxyGateway::class => ReverseProxyException::class,
        FastlyReverseProxyGateway::class => ReverseProxyException::class,
        RedisReverseProxyGateway::class => ReverseProxyException::class,
    ];

    /**
     * @var array<string>
     */
    private array $validExceptionClasses;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly Configuration $configuration,
    ) {
        // see src/Core/DevOps/StaticAnalyze/PHPStan/extension.neon for the default config
        $this->validExceptionClasses = $this->configuration->getAllowedNonDomainExceptions();
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

        if ($node->expr instanceof StaticCall) {
            return $this->validateDomainExceptionClass($node->expr, $scope);
        }

        if (!$node->expr instanceof New_) {
            return [];
        }

        \assert($node->expr->class instanceof Name);
        $exceptionClass = $node->expr->class->toString();

        if (\in_array($exceptionClass, $this->validExceptionClasses, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/shopware/platform/blob/v6.4.20.0/adr/2022-02-24-domain-exceptions.md')
                ->identifier('shopware.domainException')
                ->build(),
        ];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateDomainExceptionClass(StaticCall $node, Scope $scope): array
    {
        \assert($node->class instanceof Name);
        $exceptionClass = $node->class->toString();

        if (!\str_starts_with($exceptionClass, 'Shopware\\Core\\')) {
            return [];
        }

        $exception = $this->reflectionProvider->getClass($exceptionClass);
        if (!$exception->isSubclassOf(HttpException::class)) {
            return [
                RuleErrorBuilder::message(\sprintf('Domain exception class %s has to extend the \Shopware\Core\Framework\HttpException class', $exceptionClass))
                    ->identifier('shopware.domainException')
                    ->build(),
            ];
        }

        $reflection = $scope->getClassReflection();
        \assert($reflection !== null);
        if (!\str_starts_with($reflection->getName(), 'Shopware\\Core\\')) {
            return [];
        }

        if ($this->isRemapped($reflection->getName(), $exceptionClass)) {
            return [];
        }

        $parts = \explode('\\', $reflection->getName());

        $domain = $parts[2] ?? '';
        $sub = $parts[3] ?? '';

        $acceptedClasses = [
            \sprintf('Shopware\\Core\\%s\\%s\\%sException', $domain, $sub, $sub),
            \sprintf('Shopware\\Core\\%s\\%sException', $domain, $domain),
        ];

        foreach ($acceptedClasses as $expected) {
            if ($exceptionClass === $expected || $exception->isSubclassOf($expected)) {
                return [];
            }
        }

        // Is it in a subdomain?
        if (isset($parts[5]) && \in_array($parts[4], self::VALID_SUB_DOMAINS, true)) {
            $expectedSub = \sprintf('\\%s\\%sException', $parts[4], $parts[4]);
            if (\str_starts_with(strrev($exceptionClass), strrev($expectedSub))) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf('Expected domain exception class %s, got %s', $acceptedClasses[0], $exceptionClass))
                ->identifier('shopware.domainException')
                ->build(),
        ];
    }

    private function isRemapped(string $source, string $exceptionClass): bool
    {
        if (!\array_key_exists($source, self::REMAPPED_DOMAINS)) {
            return false;
        }

        return self::REMAPPED_DOMAINS[$source] === $exceptionClass;
    }
}
