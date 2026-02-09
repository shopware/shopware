<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait AssignArrayTrait
{
    /**
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options)
    {
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
     * Note: assignRecursive uses reflection and creates nested struct instances,
     * so it is noticeably slower than the classic shallow assign and is intended
     * for import/export and (re-)hydration scenarios rather than tight, performance-critical loops.
     */
    public function assignRecursive(array $options): static
    {
        foreach ($options as $propertyName => $value) {
            if (\is_array($value)) {
                try {
                    $type = (new \ReflectionProperty($this, $propertyName))->getType();
                    if ($type !== null && (!$type instanceof \ReflectionNamedType || !$type->isBuiltin())) {
                        $this->assignValue($propertyName, $this->createStruct($type, $value));

                        continue;
                    }
                } catch (\Throwable $e) {
                    // Ignore every error that occurs while trying to create objects, except property not exists
                    if (!preg_match('/Property .* does not exist/', $e->getMessage())) {
                        continue;
                    }
                }
            }

            $this->assignValue($propertyName, $value);
        }

        return $this;
    }

    /**
     * @param array<mixed> $value
     *
     * @return AssignArrayInterface|array<mixed>
     */
    private function createStruct(\ReflectionType $type, array $value): AssignArrayInterface|array
    {
        if (!$className = $this->getPropertyClassType([$type], AssignArrayInterface::class)) {
            return $value;
        }

        $struct = (new \ReflectionClass($className))
            ->newInstanceWithoutConstructor();

        return $struct->assignRecursive($value);
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

        try {
            // @phpstan-ignore property.dynamicName (We allow dynamic property assignment)
            $this->{$propertyName} = $value;
        } catch (\Throwable) {
        }
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
