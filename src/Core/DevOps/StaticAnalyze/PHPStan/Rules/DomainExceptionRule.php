<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\RedisReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyException;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Twig\Error\LoaderError;

/**
 * @internal
 *
 * @implements Rule<Throw_>
 */
#[Package('core')]
class DomainExceptionRule implements Rule
{
    use InTestClassTrait;

    private const VALID_EXCEPTION_CLASSES = [
        DecorationPatternException::class,
        ConstraintViolationException::class,
        PermissionDeniedException::class,
        LoaderError::class, // Twig
        ServiceNotFoundException::class, // Symfony
        UnrecoverableMessageHandlingException::class, // Symfony
        RecoverableMessageHandlingException::class, // Symfony
        NotFoundHttpException::class, // Symfony
        BadRequestException::class, // Symfony
    ];

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
        VarnishReverseProxyGateway::class => ReverseProxyException::class,
        FastlyReverseProxyGateway::class => ReverseProxyException::class,
        RedisReverseProxyGateway::class => ReverseProxyException::class,
    ];

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

        if (\in_array($exceptionClass, self::VALID_EXCEPTION_CLASSES, true)) {
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
                RuleErrorBuilder::message(\sprintf('Domain exception class %s has to extend the \Shopware\Core\Framework\HttpException class', $exceptionClass))->build(),
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

        $expected = \sprintf('Shopware\\Core\\%s\\%s\\%sException', $domain, $sub, $sub);

        if ($exceptionClass !== $expected && !$exception->isSubclassOf($expected)) {
            // Is it in a subdomain?
            if (isset($parts[5]) && \in_array($parts[4], self::VALID_SUB_DOMAINS, true)) {
                $expectedSub = \sprintf('\\%s\\%sException', $parts[4], $parts[4]);
                if (\str_starts_with(strrev($exceptionClass), strrev($expectedSub))) {
                    return [];
                }
            }

            return [
                RuleErrorBuilder::message(\sprintf('Expected domain exception class %s, got %s', $expected, $exceptionClass))->build(),
            ];
        }

        return [];
    }

    private function isRemapped(string $source, string $exceptionClass): bool
    {
        if (!\array_key_exists($source, self::REMAPPED_DOMAINS)) {
            return false;
        }

        return self::REMAPPED_DOMAINS[$source] === $exceptionClass;
    }
}
