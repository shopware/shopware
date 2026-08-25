<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build when a tagged data loader cannot satisfy the introspection contract: the class must
 * extend AbstractContentDataLoader with a resolvable `@extends` annotation, its source name must not be reserved
 * (`loader`/`config`), and its declared configSpecification() must have unique keys, types from the
 * ConfigKeySpecification::TYPES set, string-typed reference kinds, referenced types from the
 * ConfigKeySpecification::REFERENCED_TYPES set and only on a reference kind, coherent defaults (never on a
 * required key), coherent merges (a `list<string>` reference key merging into a declared `list<string>` literal
 * key, with at most one merger key claiming any given target), and no reserved key name. It also fails the build
 * when the tagged class is PHP-abstract despite a concrete service definition, when two loaders declare the same
 * source, and when a loader's source has no `content_system.config_serializer` declaring it.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemDataLoaderCompilerPass implements CompilerPassInterface
{
    /**
     * Reserved both as a loader source name and as a config key name: the binding sugar grammar's entry
     * classification consumes a `loader` (and, for hygiene, a `config`) entry, so a source or key of that name
     * would be permanently shadowed or ambiguous.
     */
    private const RESERVED_NAMES = ['loader', 'config'];

    public function process(ContainerBuilder $container): void
    {
        $loaders = $container->findTaggedServiceIds('content_system.data_loader');

        $loaderSources = [];

        foreach ($loaders as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if ($class === null || !class_exists($class)) {
                // No resolvable class to introspect; leave it for Symfony's own service validation.
                continue;
            }

            if ($definition->isAbstract()) {
                // findTaggedServiceIds() returns abstract definitions too, and such a definition is a parent template
                // rather than a service, so there is no loader here to hold to the introspection contract.
                continue;
            }

            if (!is_subclass_of($class, AbstractContentDataLoader::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($serviceId, 'content_system.data_loader', AbstractContentDataLoader::class);
            }

            if ((new \ReflectionClass($class))->isAbstract()) {
                // The two abstractness checks are independent: Definition::isAbstract() above reports only the Symfony
                // definition flag, while this one catches a PHP-abstract class registered under a concrete definition,
                // whose still-abstract members would raise a PHP Error at the static calls below.
                throw DependencyInjectionException::dataLoaderClassIsAbstract($serviceId, $class);
            }

            /** @var class-string<AbstractContentDataLoader<Struct>> $class */
            $class::extendsDescriptor();

            $this->validateSourceName($class);
            $this->validateConfigSpecification($class);

            $source = $class::getRequirementType();
            $priorLoader = $loaderSources[$source] ?? null;

            if ($priorLoader !== null) {
                throw DependencyInjectionException::dataLoaderDuplicateSource($class, $priorLoader, $source);
            }

            $loaderSources[$source] = $class;
        }

        $this->validateConfigSerializerCoverage($container, $loaderSources);
    }

    /**
     * DataLoaderConfigSerializerProvider::encode()/decode() throw when a source has no registered serializer, and
     * both the layout-write path and every FULL-mode render reach that call: a loader without a serializer breaks
     * at the first write of a layout using it and at every full-mode render of one. Failing the container build
     * instead puts the failure in front of whoever forgot the registration.
     *
     * Only this direction is checked. A serializer registered under a source no loader declares is a dead service,
     * not a defect, so failing a third party's build over one would prevent nothing.
     *
     * @param array<string, class-string<AbstractContentDataLoader<Struct>>> $loaderSources
     */
    private function validateConfigSerializerCoverage(ContainerBuilder $container, array $loaderSources): void
    {
        $serializedSources = [];

        foreach (array_keys($container->findTaggedServiceIds('content_system.config_serializer')) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if ($class === null || !class_exists($class)) {
                // No resolvable class to introspect, exactly as the loader loop above treats the same condition.
                continue;
            }

            if ($definition->isAbstract()) {
                // findTaggedServiceIds() returns abstract definitions too, and getSource() may still be abstract on
                // such a class, where a static call raises a PHP Error instead of a readable build failure.
                continue;
            }

            if (!is_subclass_of($class, AbstractContentDataLoaderConfigSerializer::class)) {
                // Not a serializer, so it declares no source; whether the tag itself is legal is not this check's call.
                continue;
            }

            $serializedSources[$class::getSource()] = true;
        }

        foreach ($loaderSources as $source => $loaderClass) {
            if (isset($serializedSources[$source])) {
                continue;
            }

            throw DependencyInjectionException::dataLoaderSourceWithoutConfigSerializer($loaderClass, $source);
        }
    }

    /**
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateSourceName(string $class): void
    {
        $source = $class::getRequirementType();

        if (\in_array($source, self::RESERVED_NAMES, true)) {
            throw DependencyInjectionException::dataLoaderReservedSource($class, $source);
        }
    }

    /**
     * configSpecification() is an instance method, but it is a constant declaration that must not depend on
     * constructor state: it is dry-run here on an instance built without its constructor, at build time and without
     * the container, exactly as extendsDescriptor() is dry-run statically.
     *
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateConfigSpecification(string $class): void
    {
        $specification = (new \ReflectionClass($class))->newInstanceWithoutConstructor()->configSpecification();

        $seen = [];
        foreach ($specification->keys as $key) {
            if (isset($seen[$key->name])) {
                throw DependencyInjectionException::dataLoaderConfigKeyDuplicate($class, $key->name);
            }
            $seen[$key->name] = true;

            if (\in_array($key->name, self::RESERVED_NAMES, true)) {
                throw DependencyInjectionException::dataLoaderReservedConfigKey($class, $key->name);
            }

            $this->validateKeyType($class, $key);
            $this->validateKeyKindType($class, $key);
            $this->validateKeyReferencedType($class, $key);
            $this->validateKeyDefault($class, $key);
        }

        $this->validateMerges($class, $specification);
    }

    /**
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateKeyType(string $class, ConfigKeySpecification $key): void
    {
        if (\in_array($key->type, ConfigKeySpecification::TYPES, true)) {
            return;
        }

        throw DependencyInjectionException::dataLoaderConfigKeyUnknownType($class, $key->name, $key->type, ConfigKeySpecification::TYPES);
    }

    /**
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateKeyKindType(string $class, ConfigKeySpecification $key): void
    {
        if ($key->kind === ConfigKeyKind::Literal) {
            return;
        }

        if ($key->type === 'string') {
            return;
        }

        throw DependencyInjectionException::dataLoaderConfigKeyInvalidType($class, $key->name, $key->kind->value, $key->type);
    }

    /**
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateKeyReferencedType(string $class, ConfigKeySpecification $key): void
    {
        if (!\in_array($key->referencedType, ConfigKeySpecification::REFERENCED_TYPES, true)) {
            throw DependencyInjectionException::dataLoaderConfigKeyUnknownReferencedType($class, $key->name, $key->referencedType, ConfigKeySpecification::REFERENCED_TYPES);
        }

        if ($key->kind === ConfigKeyKind::PropertyReference) {
            return;
        }

        if ($key->referencedType === 'string') {
            return;
        }

        throw DependencyInjectionException::dataLoaderConfigKeyReferencedTypeMisplaced($class, $key->name, $key->kind->value);
    }

    /**
     * A merge target is another key of the same specification, so this runs over the whole specification rather
     * than per key.
     *
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateMerges(string $class, LoaderConfigSpecification $specification): void
    {
        $byName = [];
        foreach ($specification->keys as $key) {
            $byName[$key->name] = $key;
        }

        $claimedBy = [];
        foreach ($specification->keys as $key) {
            if ($key->mergesInto === null) {
                continue;
            }

            if ($key->kind !== ConfigKeyKind::PropertyReference) {
                throw DependencyInjectionException::dataLoaderConfigKeyInvalidMerge($class, $key->name, \sprintf('only a propertyReference key can merge into another key, this one has kind "%s"', $key->kind->value));
            }

            if ($key->referencedType !== 'list<string>') {
                throw DependencyInjectionException::dataLoaderConfigKeyInvalidMerge($class, $key->name, \sprintf('a merging key must reference a "list<string>" value, this one references "%s"', $key->referencedType));
            }

            if ($key->mergesInto === $key->name) {
                throw DependencyInjectionException::dataLoaderConfigKeyInvalidMerge($class, $key->name, 'a key cannot merge into itself');
            }

            $target = $byName[$key->mergesInto] ?? null;

            if ($target === null) {
                throw DependencyInjectionException::dataLoaderConfigKeyInvalidMerge($class, $key->name, \sprintf('the merge target "%s" is not declared in the same specification', $key->mergesInto));
            }

            if ($target->kind !== ConfigKeyKind::Literal || $target->type !== 'list<string>') {
                throw DependencyInjectionException::dataLoaderConfigKeyInvalidMerge($class, $key->name, \sprintf('the merge target "%s" must be a literal key of type "list<string>", got kind "%s" of type "%s"', $target->name, $target->kind->value, $target->type));
            }

            $priorClaimant = $claimedBy[$key->mergesInto] ?? null;

            if ($priorClaimant !== null) {
                throw DependencyInjectionException::dataLoaderConfigKeyInvalidMerge($class, $key->name, \sprintf('the merge target "%s" is already claimed by key "%s"; at most one merger key may target a given key', $key->mergesInto, $priorClaimant));
            }

            $claimedBy[$key->mergesInto] = $key->name;
        }
    }

    /**
     * @param class-string<AbstractContentDataLoader<Struct>> $class
     */
    private function validateKeyDefault(string $class, ConfigKeySpecification $key): void
    {
        if ($key->required && $key->hasDefault) {
            throw DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch($class, $key->name, 'a required key must not declare a default (required means: no default and the loader cannot produce without it)');
        }

        if (!$key->hasDefault && $key->default !== null) {
            throw DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch($class, $key->name, 'a key without a declared default (hasDefault: false) must not carry a default value');
        }

        if (!$key->hasDefault) {
            return;
        }

        // A null default with hasDefault: true is always legal (several core keys genuinely default to null).
        if ($key->default === null) {
            return;
        }

        if ($this->defaultMatchesType($key->type, $key->default)) {
            return;
        }

        throw DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
            $class,
            $key->name,
            \sprintf('the default value of PHP type "%s" does not match the declared type "%s"', \get_debug_type($key->default), $key->type)
        );
    }

    private function defaultMatchesType(string $type, mixed $default): bool
    {
        return match ($type) {
            'string' => \is_string($default),
            'integer' => \is_int($default),
            'number' => \is_int($default) || \is_float($default),
            'boolean' => \is_bool($default),
            'list<string>' => \is_array($default) && array_is_list($default) && array_filter($default, 'is_string') === $default,
            'map' => \is_array($default),
            default => false,
        };
    }
}
