<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Decoratable;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AnnotationBasedRuleHelper;

/**
 * @implements Rule<Class_>
 */
class DecoratableNotDirectlyDependetRule implements Rule
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
        $errors = [];

        foreach ($node->getProperties() as $property) {
            foreach ($property->props as $prop) {
                $propReflections = $class->getProperty($prop->name->name, $scope);
                $this->containsDecoratableTypeDependence($propReflections->getReadableType(), $errors, $class->getName(), $property->getStartLine());
            }
        }

        foreach ($node->getMethods() as $method) {
            $methodReflection = $class->getMethod($method->name->name, $scope);
            foreach ($methodReflection->getVariants() as $variant) {
                $this->containsDecoratableTypeDependence($variant->getReturnType(), $errors, $class->getName(), $method->getStartLine());

                foreach ($variant->getParameters() as $param) {
                    $this->containsDecoratableTypeDependence($param->getType(), $errors, $class->getName(), $method->getStartLine());
                }
            }
        }

        return $errors;
    }

    /**
     * @param string[]|RuleError[] $errors
     */
    private function containsDecoratableTypeDependence(Type $type, array &$errors, string $originalClassname, int $startLine): void
    {
        foreach ($type->getReferencedClasses() as $className) {
            $class = $this->broker->getClass($className);
            if (!$class->isInterface() && AnnotationBasedRuleHelper::isClassTaggedWithAnnotation($class, AnnotationBasedRuleHelper::DECORATABLE_ANNOTATION)) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'The service "%s" has a direct dependency on decoratable service "%s", but must only depend on it\'s interface.',
                        $originalClassname,
                        $class->getName()
                    )
                )->line($startLine)
                ->build();
            }
        }
    }
}
