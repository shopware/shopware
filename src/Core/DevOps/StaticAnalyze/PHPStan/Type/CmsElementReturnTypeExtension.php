<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class CmsElementReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /**
     * @var array<string, Type>|null
     */
    private ?array $types = null;

    public function __construct(private readonly TypeStringResolver $typeResolver, private readonly string $mappingFile)
    {
    }

    public function getClass(): string
    {
        return CmsPageEntity::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getDeclaringClass()->getName() === CmsPageEntity::class
            && \in_array($methodReflection->getName(), ['getFirstElementOfType', 'getElementsOfType'], true);
    }

    /**
     * @param Scope&DependencyTracker $scope
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $scope->trackFileDependency($this->mappingFile);
        if ($this->types === null) {
            $this->types = [];
            /** @var array{types: array<string, string>} $mapping */
            $mapping = json_decode((new Filesystem())->readFile($this->mappingFile), true, flags: \JSON_THROW_ON_ERROR);
            foreach ($mapping['types'] as $name => $type) {
                $this->types[$name] = $this->typeResolver->resolve($type);
            }
        }
        $argument = $methodCall->getArgs()[0] ?? null;
        if ($argument === null) {
            return null;
        }
        $names = $scope->getType($argument->value)->getConstantStrings();
        if ($names === []) {
            return null;
        }
        $slots = [];
        foreach ($names as $name) {
            $slots[] = new GenericObjectType(CmsSlotEntity::class, [$this->types[$name->getValue()] ?? new ObjectType(Struct::class)]);
        }
        $slot = TypeCombinator::union(...$slots);

        return $methodReflection->getName() === 'getFirstElementOfType'
            ? TypeCombinator::union($slot, new NullType())
            : TypeCombinator::intersect(new ArrayType(new IntegerType(), $slot), new AccessoryArrayListType());
    }
}
