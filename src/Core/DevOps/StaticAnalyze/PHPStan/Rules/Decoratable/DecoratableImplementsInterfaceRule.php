<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Decoratable;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AnnotationBasedRuleHelper;

/**
 * @implements Rule<Class_>
 */
class DecoratableImplementsInterfaceRule implements Rule
{
    /**
     * @var Broker
     */
    private $broker;

    public function __construct(Broker $broker)
    {
        $this->broker = $broker;
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!isset($node->namespacedName)) {
            // skip anonymous classes
            return [];
        }

        $class = $this->broker->getClass($scope->resolveName($node->namespacedName));
        if (!AnnotationBasedRuleHelper::isClassTaggedWithAnnotation($class, AnnotationBasedRuleHelper::DECORATABLE_ANNOTATION)) {
            return [];
        }

        if ($this->implementsInterface($class)) {
            return [];
        }

        return [
            sprintf(
                'The service "%s" is marked as "@Decoratable", but does not implement an interface.',
                $class->getName()
            ),
        ];
    }

    private function implementsInterface(ClassReflection $class): bool
    {
        if (!empty($class->getInterfaces())) {
            return true;
        }

        $parentClass = $class->getParentClass();
        if ($parentClass) {
            return $this->implementsInterface($parentClass);
        }

        return false;
    }
}
