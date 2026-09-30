<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Collects the methods of test classes that push a request onto a `RequestStack`, as `Class::method`. Trait
 * code is analysed in the context of each class using the trait, so the using class is the one recorded.
 *
 * @implements Collector<MethodCall, string>
 *
 * @internal
 */
#[Package('framework')]
class RequestStackPushCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(Node $node, Scope $scope): ?string
    {
        if (!$node->name instanceof Identifier || $node->name->toLowerString() !== 'push') {
            return null;
        }

        $class = $scope->getClassReflection();
        if ($class === null || !TestRuleHelper::isTestClass($class)) {
            return null;
        }

        if (!(new ObjectType(RequestStack::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return null;
        }

        return $class->getName() . '::' . ($scope->getFunctionName() ?? '');
    }
}
