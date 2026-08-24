<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects assignments that will be incompatible with an announced property change.
 *
 * @implements Rule<Expr>
 *
 * @internal
 */
#[Package('framework')]
class FuturePropertyCompatibilityRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly TypeStringResolver $typeStringResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Assign || !$node->var instanceof PropertyFetch && !$node->var instanceof StaticPropertyFetch) {
            return [];
        }

        if (!$node->var->name instanceof Identifier) {
            return [];
        }

        $propertyName = $node->var->name->toString();
        foreach ($this->properties($node->var, $propertyName, $scope) as $property) {
            if ($this->isDeclaredInCurrentClass($property, $scope)) {
                continue;
            }

            foreach ($property->getAttributes(BecomesReadonly::class) as $attribute) {
                /** @var BecomesReadonly $becomesReadonly */
                $becomesReadonly = $attribute->newInstance();

                return [$this->error(
                    \sprintf(
                        'Property "%s::$%s" will become readonly in %s. Stop assigning to it outside the declaring class.',
                        $property->getDeclaringClass()->getName(),
                        $property->getName(),
                        $becomesReadonly->version,
                    ),
                    $node->getStartLine(),
                    'propertyBecomesReadonly',
                )];
            }

            foreach ($property->getAttributes(PropertyTypeNarrowing::class) as $attribute) {
                /** @var PropertyTypeNarrowing $typeNarrowing */
                $typeNarrowing = $attribute->newInstance();
                $announcedType = $this->resolveType($typeNarrowing->newType);
                if ($announcedType === null) {
                    continue;
                }

                $actualType = $scope->getType($node->expr);
                if (!$announcedType->isSuperTypeOf($actualType)->no()) {
                    continue;
                }

                return [$this->error(
                    \sprintf(
                        'Property "%s::$%s" will be narrowed to %s in %s, but %s is assigned. Assign %s to stay compatible with both versions.',
                        $property->getDeclaringClass()->getName(),
                        $property->getName(),
                        $typeNarrowing->newType,
                        $typeNarrowing->version,
                        $actualType->describe(VerbosityLevel::typeOnly()),
                        $typeNarrowing->newType,
                    ),
                    $node->getStartLine(),
                    'propertyTypeNarrowing',
                )];
            }
        }

        return [];
    }

    /**
     * @return iterable<\ReflectionProperty>
     */
    private function properties(PropertyFetch|StaticPropertyFetch $fetch, string $propertyName, Scope $scope): iterable
    {
        $classes = [];
        if ($fetch instanceof PropertyFetch) {
            $classes = $scope->getType($fetch->var)->getObjectClassReflections();
        } elseif ($fetch->class instanceof Name) {
            $className = $scope->resolveName($fetch->class);
            if ($this->reflectionProvider->hasClass($className)) {
                $classes[] = $this->reflectionProvider->getClass($className);
            }
        }

        foreach ($classes as $class) {
            $native = $class->getNativeReflection();
            if ($native->hasProperty($propertyName)) {
                yield $native->getProperty($propertyName);
            }
        }
    }

    private function isDeclaredInCurrentClass(\ReflectionProperty $property, Scope $scope): bool
    {
        return $scope->isInClass()
            && $scope->getClassReflection()->getName() === $property->getDeclaringClass()->getName();
    }

    private function resolveType(string $type): ?Type
    {
        try {
            return $this->typeStringResolver->resolve($type);
        } catch (\Throwable) {
            return null;
        }
    }

    private function error(string $message, int $line, string $identifier): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('shopware.futureIncompatibility.' . $identifier)
            ->line($line)
            ->build();
    }
}
