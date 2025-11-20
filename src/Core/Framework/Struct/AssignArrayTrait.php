<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait AssignArrayTrait
{
    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $fallbackSorting will be added
     * @deprecated tag:v6.8.0 - reason:return-type-change - will use "strong" return type `self`
     *
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options/* , bool $deep = false */)/* : self */
    {
        $deep = \func_num_args() >= 2 && func_get_arg(1);
        if ($deep) {
            return $this->assignRecursive($options);
        }

        foreach ($options as $key => $value) {
            if ($key === 'id' && method_exists($this, 'setId')) {
                $this->setId($value);

                continue;
            }

            try {
                // @phpstan-ignore property.dynamicName (We allow dynamic assignment of all properties)
                $this->$key = $value;
            } catch (\Error|\Exception) {
                // nth
            }
        }

        return $this;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function assignRecursive(array $options): self
    {
        foreach ($options as $propertyName => $value) {
            try {
                if ($value === null || $value === [] || \is_scalar($value)) {
                    $this->assignValue($propertyName, $value);

                    continue;
                }

                $property = new \ReflectionProperty($this, $propertyName);
                if (!($type = $property->getType())) {
                    $this->assignValue($propertyName, $value);

                    continue;
                }

                if (\is_array($value) && (!$type instanceof \ReflectionNamedType || !$type->isBuiltin())) {
                    $this->assignValue($propertyName, $this->createStruct($type, $value));

                    continue;
                }

                $this->assignValue($propertyName, $value);
            } catch (\Error|\Exception) {
                // nth
            }
        }

        return $this;
    }

    private function createStruct(\ReflectionType $type, array $value): Struct|array
    {
        if (!$className = $this->getPropertyClassType([$type], AssignArrayInterface::class)) {
            return $value;
        }

        // Only structs, without constructor parameters can be created and assigned.
        $struct = new $className();
        if ($struct instanceof Collection) {
            $struct->addFromAssociative($value);
        } else {
            $struct->assign($value, true);
        }

        return $struct;
    }

    private function assignValue(string $propertyName, mixed $value): void
    {
        try {
            $setterMethod = 'set' . \ucfirst($propertyName);
            // @phpstan-ignore method.dynamicName (We allow dynamic setter call of all properties)
            $this->{$setterMethod}($value);

            return;
        } catch (\Throwable) {
        }

        // @phpstan-ignore property.dynamicName (We allow dynamic property assignment, if class has \AllowDynamicProperties attribute)
        $this->{$propertyName} = $value;
    }

    /**
     * @template T
     *
     * @param \ReflectionType[] $types
     * @param class-string<T> $expectedClass
     *
     * @return (class-string&T)|class-string<T>|null
     */
    private function getPropertyClassType(array $types, string $expectedClass): ?string
    {
        foreach ($types as $type) {
            $type = match (true) {
                $type instanceof \ReflectionNamedType => $type,
                $type instanceof \ReflectionUnionType => $this->getPropertyClassType($type->getTypes(), $expectedClass),
                default => null,
            };

            if ($type === null) {
                continue;
            }

            if ($type instanceof \ReflectionNamedType) {
                if ($type->isBuiltin()) {
                    continue;
                }

                $type = $type->getName();
            }

            if (\class_exists($type) && \is_a($type, $expectedClass, true)) {
                return $type;
            }
        }

        return null;
    }
}
