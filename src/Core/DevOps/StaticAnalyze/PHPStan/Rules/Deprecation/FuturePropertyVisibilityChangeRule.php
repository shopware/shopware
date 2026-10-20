<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects Core property accesses that will be outside the announced visibility scope.
 *
 * @implements Rule<Expr>
 *
 * @internal
 */
#[Package('framework')]
class FuturePropertyVisibilityChangeRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof PropertyFetch && !$node instanceof StaticPropertyFetch) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        $propertyName = $node->name->toString();
        foreach ($this->propertyClasses($node, $scope) as $class) {
            $native = $class->getNativeReflection();
            if (!$native->hasProperty($propertyName)) {
                continue;
            }

            $property = $native->getProperty($propertyName);
            foreach ($property->getAttributes(VisibilityChange::class) as $attribute) {
                /** @var VisibilityChange $visibilityChange */
                $visibilityChange = $attribute->newInstance();
                if ($this->canAccess($visibilityChange->newVisibility, $class, $scope)) {
                    continue;
                }

                return [$this->error(
                    \sprintf(
                        'Property "%s::$%s" will become %s in %s. This access will break; stop accessing it from outside that scope.',
                        $class->getDisplayName(),
                        $propertyName,
                        $visibilityChange->newVisibility,
                        $visibilityChange->version,
                    ),
                    $node->getStartLine(),
                )];
            }
        }

        return [];
    }

    /**
     * @return list<ClassReflection>
     */
    private function propertyClasses(PropertyFetch|StaticPropertyFetch $node, Scope $scope): array
    {
        if ($node instanceof PropertyFetch) {
            return $scope->getType($node->var)->getObjectClassReflections();
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);

        return $this->reflectionProvider->hasClass($className) ? [$this->reflectionProvider->getClass($className)] : [];
    }

    private function canAccess(string $visibility, ClassReflection $class, Scope $scope): bool
    {
        $caller = $scope->isInClass() ? $scope->getClassReflection() : null;

        return match ($visibility) {
            'protected' => $caller !== null && ($caller->getName() === $class->getName() || $caller->isSubclassOfClass($class)),
            'private' => $caller !== null && $caller->getName() === $class->getName(),
            default => true,
        };
    }

    private function error(string $message, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('shopware.futureIncompatibility.propertyVisibilityChange')
            ->line($line)
            ->build();
    }
}
