<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use GuzzleHttp\Client;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('core')]
class MockingSimpleObjectsNotAllowedRule implements Rule
{
    private const DISALLOWED_CLASSES = [
        Struct::class,
        Context::class,
        Request::class,
        ParameterBag::class,
        Client::class,
    ];

    private const ALLOWED_CLASSES = [
        SalesChannelContext::class,
        EntitySearchResult::class,
    ];

    private const MOCK_METHODS = ['createMock', 'createMockObject', 'createStub', 'createPartialMock', 'createConfiguredMock', 'createTestProxy'];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isTestClass($scope)) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        if (!\in_array((string) $node->name, self::MOCK_METHODS, true)) {
            return [];
        }

        $mockedClassString = $this->resolveClassName($node->getArgs()[0]->value);

        if ($mockedClassString === null || !$this->reflectionProvider->hasClass($mockedClassString)) {
            return [];
        }

        $mockedClass = $this->reflectionProvider->getClass($mockedClassString);

        if (!$this->isBlacklisted($mockedClass)) {
            return [];
        }

        return [
            sprintf('Mocking of %s is not allowed. The object is very basic and can be constructed', $mockedClassString),
        ];
    }

    private function isTestClass(Scope $node): bool
    {
        if ($node->getClassReflection() === null) {
            return false;
        }

        $namespace = $node->getClassReflection()->getName();

        if (!\str_contains($namespace, 'Shopware\\Tests\\Unit\\') && !\str_contains($namespace, 'Shopware\\Tests\\Migration\\')) {
            return false;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }

    private function resolveClassName(Node $node): ?string
    {
        switch (true) {
            case $node instanceof String_:
                return (string) $node->value;
            case $node instanceof ClassConstFetch:
                if ($node->class instanceof Name) {
                    return (string) $node->class;
                }

                return null;
            default:
                return null;
        }
    }

    private function isBlacklisted(ClassReflection $class): bool
    {
        if (\in_array($class->getName(), self::ALLOWED_CLASSES, true)) {
            return false;
        }

        if (\in_array($class->getName(), self::DISALLOWED_CLASSES, true)) {
            return true;
        }

        foreach ($class->getParentClassesNames() as $parentClassesName) {
            if (\in_array($parentClassesName, self::ALLOWED_CLASSES, true)) {
                return false;
            }

            if (\in_array($parentClassesName, self::DISALLOWED_CLASSES, true)) {
                return true;
            }
        }

        return false;
    }
}
