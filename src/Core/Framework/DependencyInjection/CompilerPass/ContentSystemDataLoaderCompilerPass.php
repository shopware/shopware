<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build when a tagged data loader cannot satisfy the introspection contract: the class must
 * extend AbstractContentDataLoader with a resolvable `@extends` annotation, its source name must not be reserved
 * (`loader`/`config`), and its declared configSpecification() must have unique keys, types from the
 * ConfigKeySpecification::TYPES set, string-typed reference kinds, coherent defaults (never on a required key),
 * and no reserved key name.
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

        foreach ($loaders as $serviceId => $tags) {
            $class = $container->getDefinition($serviceId)->getClass();

            if ($class === null || !class_exists($class)) {
                // No resolvable class to introspect; leave it for Symfony's own service validation.
                continue;
            }

            if (!is_subclass_of($class, AbstractContentDataLoader::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($serviceId, 'content_system.data_loader', AbstractContentDataLoader::class);
            }

            /** @var class-string<AbstractContentDataLoader<Struct>> $class */
            $class::extendsDescriptor();

            $this->validateSourceName($class);
            $this->validateConfigSpecification($class);
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
            $this->validateKeyDefault($class, $key);
        }
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
