<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Feature;
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

            $this->assignPropertyDirectly($key, $value);
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
                } catch (\Error $error) {
                    if (Feature::isActive('v6.8.0.0')) {
                        /** @phpstan-ignore shopware.domainException (If trait is used directly, PHPStan complains about the wrong domain) */
                        throw StructException::assignTypeError($error);
                    }

                    Feature::triggerDeprecationOrThrow(
                        'v6.8.0.0',
                        'AssignRecursive will fail with next major: ' . $error->getMessage(),
                        '6.7.13.0'
                    );
                } catch (\ReflectionException $exception) {
                    // Allow dynamic property creation
                    if (!preg_match('/Property .* does not exist/', $exception->getMessage())) {
                        if (Feature::isActive('v6.8.0.0')) {
                            throw $exception;
                        }

                        Feature::triggerDeprecationOrThrow(
                            'v6.8.0.0',
                            'AssignRecursive will fail with next major: ' . $exception->getMessage(),
                            '6.7.13.0'
                        );
                        continue;
                    }
                } catch (\Throwable $e) {
                    /** @deprecated tag:v6.8.0 remove this catch branch */
                    Feature::triggerDeprecationOrThrow(
                        'v6.8.0.0',
                        'AssignRecursive will fail with next major: ' . $e->getMessage(),
                        '6.7.13.0'
                    );
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

        return (new \ReflectionClass($className))->newInstanceWithoutConstructor()->assignRecursive($value);
    }

    private function assignValue(string $propertyName, mixed $value): void
    {
        try {
            $setterMethod = 'set' . \ucfirst($propertyName);
            // @phpstan-ignore method.dynamicName (We allow dynamic setter call of all properties)
            $this->$setterMethod($value);

            return;
        } catch (\Throwable) {
            // Direct property assignment will be tried and will fail if type is still incorrect
        }

        $this->assignPropertyDirectly($propertyName, $value);
    }

    private function assignPropertyDirectly(string $propertyName, mixed $value): void
    {
        try {
            // @phpstan-ignore property.dynamicName (We allow dynamic assignment of all properties)
            $this->$propertyName = $value;
        } catch (\TypeError $error) {
            if (Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopware.domainException (If trait is used directly, PHPStan complains about the wrong domain) */
                throw StructException::assignTypeError($error);
            }

            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Assign will fail with next major: ' . $error->getMessage(),
                '6.7.13.0'
            );
        } catch (\Throwable $e) {
            /** @deprecated tag:v6.8.0 remove this catch branch */
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Assign will fail with next major: ' . $e->getMessage(),
                '6.7.13.0'
            );
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
