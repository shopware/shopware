<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal Prototype for replacing PartialEntity with PHP 8.4 lazy ghost objects (requires PHP >= 8.4 / Shopware 6.8)
 *
 * Instead of returning a generic PartialEntity for partially loaded data (Criteria::addFields()),
 * this factory creates a lazy ghost object (https://www.php.net/manual/en/language.oop5.lazy-objects.php)
 * of the real entity class and pre-initializes all loaded fields on it.
 *
 * Consumers can therefore work with the real entity type (real type hints, real getters like getName())
 * without `has()`/`get()` pre-checks. Accessing a property that was NOT loaded triggers the ghost
 * initializer, which throws an exception that tells the API user exactly which field has to be added
 * to the request/criteria.
 */
#[Package('framework')]
class LazyEntityFactory
{
    /**
     * @var array<string, array<string, \ReflectionProperty|null>>
     */
    private static array $propertyCache = [];

    /**
     * Converts a hydrated PartialEntity into a lazy ghost object of the real entity class.
     *
     * @param class-string<Entity> $entityClass
     */
    public static function fromPartial(string $entityClass, PartialEntity $partial): Entity
    {
        $data = $partial->all();

        $entityName = $partial->getInternalEntityName() ?? $entityClass;
        $loadedFields = array_values(array_diff(array_keys($data), ['translated']));

        $reflection = new \ReflectionClass($entityClass);

        // @phpstan-ignore method.notFound (PHP 8.4 lazy object API, not known to PHPStan with PHP 8.2 as minimum version)
        $entity = $reflection->newLazyGhost(static function () use ($entityName, $loadedFields): void {
            throw DataAbstractionLayerException::partialFieldNotLoaded(
                self::determineAccessedField(),
                $entityName,
                $loadedFields
            );
        });

        // technical base data, which is always available on hydrated entities
        self::initialize($entityClass, $entity, '_uniqueIdentifier', $partial->getUniqueIdentifier());
        self::initialize($entityClass, $entity, '_entityName', $partial->getInternalEntityName());
        self::initialize($entityClass, $entity, '_fieldVisibility', null);
        self::initialize($entityClass, $entity, 'extensions', $partial->getExtensions());
        self::initialize($entityClass, $entity, 'translated', $partial->getTranslated());

        foreach ($data as $property => $value) {
            if ($property === 'translated') {
                continue;
            }

            // partially loaded to-one associations become lazy ghosts of their real entity class as well
            if ($value instanceof PartialEntity) {
                $associationClass = self::resolveEntityClass($entityClass, $property);

                if ($associationClass !== null) {
                    $value = self::fromPartial($associationClass, $value);
                }
            }

            self::initialize($entityClass, $entity, $property, $value);
        }

        return $entity;
    }

    /**
     * Pre-initializes a single property on the lazy ghost without triggering the initializer.
     *
     * @param class-string<Entity> $entityClass
     */
    private static function initialize(string $entityClass, Entity $entity, string $property, mixed $value): void
    {
        $reflectionProperty = self::reflectProperty($entityClass, $property);

        // property does not exist on the real entity class (e.g. runtime mapped values) - not covered by the prototype
        if ($reflectionProperty === null) {
            return;
        }

        // @phpstan-ignore method.notFound (PHP 8.4 lazy object API, not known to PHPStan with PHP 8.2 as minimum version)
        $reflectionProperty->setRawValueWithoutLazyInitialization($entity, $value);
    }

    /**
     * Resolves a property reflection across the whole class hierarchy, as private properties
     * of parent classes (e.g. Entity::$_entityName) are not accessible via the child class reflection.
     *
     * @param class-string $class
     */
    private static function reflectProperty(string $class, string $property): ?\ReflectionProperty
    {
        if (\array_key_exists($property, self::$propertyCache[$class] ?? [])) {
            return self::$propertyCache[$class][$property];
        }

        $found = null;
        for ($reflection = new \ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            if ($reflection->hasProperty($property) && $reflection->getProperty($property)->getDeclaringClass()->getName() === $reflection->getName()) {
                $found = $reflection->getProperty($property);

                break;
            }
        }

        return self::$propertyCache[$class][$property] = $found;
    }

    /**
     * Resolves the real entity class of a to-one association from the declared property type.
     *
     * @param class-string $class
     *
     * @return class-string<Entity>|null
     */
    private static function resolveEntityClass(string $class, string $property): ?string
    {
        $type = self::reflectProperty($class, $property)?->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $typeClass = $type->getName();

        return is_a($typeClass, Entity::class, true) ? $typeClass : null;
    }

    /**
     * Determines which field was accessed on the lazy ghost by inspecting the backtrace of the
     * ghost initializer. Covers the dedicated getters (getName(), isActive(), ...), the generic
     * accessors (get('name'), getTranslation('name')) and magic property access ($entity->name).
     */
    private static function determineAccessedField(): string
    {
        foreach (debug_backtrace(0, 10) as $frame) {
            $class = $frame['class'] ?? null;

            if ($class === null || !is_a($class, Entity::class, true)) {
                continue;
            }

            $function = $frame['function'];

            if (\in_array($function, ['get', 'getTranslation', '__get', '__isset'], true) && \is_string($frame['args'][0] ?? null)) {
                return $frame['args'][0];
            }

            if (preg_match('/^(get|is|has)([A-Z]\w*)$/', $function, $matches) === 1) {
                return lcfirst($matches[2]);
            }
        }

        return 'unknown';
    }
}
