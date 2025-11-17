<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait AssignArrayTrait
{
    /**
     * @deprecated tag:v6.8.0 added parameter $recursive will be removed - recursive will be native
     *
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options/* , bool $recursive = false */)
    {
        $recursive = \func_num_args() > 2 && func_get_arg(1);
        if ($recursive || Feature::isActive('v6.8.0.0')) {
            $this->assignRecursive($options);

            return $this;
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
        foreach ($options as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $propertyName = self::denormalize((string) $key);
            if (!\property_exists($this, $propertyName)) {
                continue;
            }

            try {
                $property = new \ReflectionProperty($this, $propertyName);
                if (!$type = $property->getType()) {
                    $this->assignValue($propertyName, $value);

                    continue;
                }

                if (\is_array($value) && $className = $this->getPropertyClassType([$type], Collection::class)) {
                    $this->assignValue($propertyName, $className::createFromAssociative($value));

                    continue;
                }

                if (\is_array($value) && $className = $this->getPropertyClassType([$type], Struct::class)) {
                    $struct = (new \ReflectionClass($className))
                        ->newInstanceWithoutConstructor()
                        ->assign($value, true);

                    $this->assignValue($propertyName, $struct);

                    continue;
                }

                if (\is_string($value) && $type instanceof \ReflectionNamedType && is_a($type->getName(), \DateTimeInterface::class, true)) {
                    $this->assignValue($propertyName, new \DateTime($value));

                    continue;
                }

                $this->assignValue($propertyName, $value);
            } catch (\Error|\Exception) {
                // nth
            }
        }

        return $this;
    }

    /**
     * Convert from camelCase to snake_case.
     */
    private static function denormalize(string $propertyName): string
    {
        /** @phpstan-ignore-next-line argument.type */
        return lcfirst(preg_replace_callback('/(^|_|\.)+(.)/', fn ($match) => ($match[1] === '.' ? '_' : '') . strtoupper($match[2]), $propertyName));
    }

    private function assignValue(string $propertyName, mixed $value): void
    {
        $setterMethod = \sprintf('set%s', \ucfirst($propertyName));

        if (\method_exists($this, $setterMethod)) {
            // @phpstan-ignore method.dynamicName (We allow dynamic setter call of all properties)
            $this->{$setterMethod}($value);
        } else {
            // @phpstan-ignore property.dynamicName (We allow dynamic assignment of all properties)
            $this->{$propertyName} = $value;
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
