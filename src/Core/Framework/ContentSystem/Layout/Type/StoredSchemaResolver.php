<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type;

use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\ResolvedByLoaderBranch;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * What an element of one type STORES, keyed by stored key: the counterpart to the type spec's `properties`,
 * which publishes the *hydrated* output schema. Served as the `storageSchema` fold on each type entry of
 * `GET /api/_info/content-system-element-types.json`.
 *
 * Three tiers contribute an entry, and a later one overwrites an earlier one on the same key: `config` <
 * `resolvedByStorage` < `property`. A declared property is the most specific statement about a stored key, so
 * it wins; between the two binding-derived tiers the resolvedBy shorthand's own storage key is the more
 * specific one.
 *
 * The two binding-derived tiers are one traversal of this type's binding specifications, discriminated per
 * config key: an entry is `resolvedByStorage` when its specification is the type's synthesized default
 * ({@see BindingSpecification::isDefault()}:
 * `id === type`, which holds exactly for what the `resolvedBy` shorthand synthesizes, because an authored
 * `bindings:` key equal to the type name is rejected as a reserved id) AND the key is the one the synthesizer
 * treats as the storage key ({@see ResolvedByLoaderBranch::STORAGE_KEY_CONFIG_KEY}). Every other
 * propertyReference key is a plain `config` entry.
 *
 * Two deliberate omissions on those two tiers:
 * - `type` is the key's `referencedType`, never its `type`. On a propertyReference key `type` describes the
 *   token (a string naming a property) while `referencedType` describes the value stored under that token
 *   (`string` or `list<string>`). Publishing `type` would tell a client `string` where the stored value is a
 *   list of ids.
 * - no `default` key at all. A `ConfigKeySpecification::$default` on a propertyReference key is a default
 *   *token* (a property name), not a default stored value, so emitting it as `default` would be false.
 *
 * No error path guards {@see DataLoaderProvider::get()}, which throws on an unregistered source: a registered
 * binding specification always names a registered loader, because
 * {@see TypeConsistentBindingSpecification} resolves
 * every `resolves` entry's produced type through the loader at load time and
 * {@see DatabaseBindingSpecificationLoader} skips a row
 * that fails validation. If that invariant ever breaks, the throw is the correct outcome: a swallowed loader
 * would publish a storage schema silently missing keys, indistinguishable from a type that stores nothing.
 *
 * @internal
 *
 * @phpstan-type StoredSchemaEntry = array{kind: string, type: string, required: bool, default?: string|int|float|bool}
 */
#[Package('framework')]
final readonly class StoredSchemaResolver
{
    private const KIND_PROPERTY = 'property';

    private const KIND_RESOLVED_BY_STORAGE = 'resolvedByStorage';

    private const KIND_CONFIG = 'config';

    public function __construct(
        private AbstractContentSystemBindingSpecificationRegistry $bindingSpecificationRegistry,
        private DataLoaderProvider $dataLoaderProvider,
    ) {
    }

    /**
     * @return array<string, StoredSchemaEntry> keyed by stored key
     */
    public function resolve(ContentSystemElementTypeSpecification $type): array
    {
        $entries = [];

        // One traversal, bucketed by kind, rather than one per kind: the registry promises no stable
        // snapshot (the leaf reloads every loader on each read), so two traversals could observe two
        // different snapshots and produce a map matching neither.
        $binding = $this->bindingEntries($type);

        // Assigned key by key rather than merged: array_merge and the spread operator renumber an
        // integer-like key, silently renaming it.
        foreach ($binding[self::KIND_CONFIG] as $key => $entry) {
            $entries[$key] = $entry;
        }

        foreach ($binding[self::KIND_RESOLVED_BY_STORAGE] as $key => $entry) {
            $entries[$key] = $entry;
        }

        foreach ($this->propertyEntries($type) as $key => $entry) {
            $entries[$key] = $entry;
        }

        return $entries;
    }

    /**
     * A declared primitive property is stored under its own key. A declared FQCN/object/union property is not:
     * nothing is ever stored under the reference key itself, so it contributes no entry.
     *
     * @return array<string, StoredSchemaEntry>
     */
    private function propertyEntries(ContentSystemElementTypeSpecification $type): array
    {
        $entries = [];

        foreach ($type->properties() as $key => $property) {
            $propertyType = $property->type();
            $declared = $propertyType->type();

            // isPrimitive() already implies a string (it matches against the primitive string set, so a list
            // declaration never passes); the is_string() check restates that for the analyzer.
            if (!$propertyType->isPrimitive() || !\is_string($declared)) {
                continue;
            }

            $entry = [
                'kind' => self::KIND_PROPERTY,
                'type' => $declared,
                'required' => $property->required(),
            ];

            $default = $propertyType->default();

            if ($default !== null) {
                $entry['default'] = $default;
            }

            $entries[(string) $key] = $entry;
        }

        return $entries;
    }

    /**
     * The binding-derived entries, bucketed by kind: every propertyReference config key of every loader this
     * type's binding specifications wire, keyed by the reference TOKEN (the stored key), not by the config key
     * name.
     *
     * @return array{config: array<string, StoredSchemaEntry>, resolvedByStorage: array<string, StoredSchemaEntry>}
     */
    private function bindingEntries(ContentSystemElementTypeSpecification $type): array
    {
        $buckets = [self::KIND_CONFIG => [], self::KIND_RESOLVED_BY_STORAGE => []];
        $properties = $type->properties();

        foreach ($this->bindingSpecificationRegistry->byType($type->name()) as $specification) {
            foreach ($specification->resolves() as $binding) {
                $loader = $this->dataLoaderProvider->get($binding->loader);

                foreach ($loader->configSpecification()->keysOfKind(ConfigKeyKind::PropertyReference) as $key) {
                    $token = $binding->config[$key->name] ?? ($key->hasDefault ? $key->default : null);

                    if (!$this->namesStoredKey($properties, $token)) {
                        continue;
                    }

                    $kind = $specification->isDefault() && $key->name === ResolvedByLoaderBranch::STORAGE_KEY_CONFIG_KEY
                        ? self::KIND_RESOLVED_BY_STORAGE
                        : self::KIND_CONFIG;

                    $buckets[$kind][$token] = [
                        'kind' => $kind,
                        'type' => $key->referencedType,
                        'required' => $key->required,
                    ];
                }
            }
        }

        return $buckets;
    }

    /**
     * Whether the token can name a stored key at all. Three ways it cannot:
     *
     * - a non-string or empty token, mirroring `LoaderInputResolver::dereference()`, which treats one as
     *   unresolvable: it names no stored key, so there is nothing to publish;
     * - an integer-like token, which no stored key can be: `StoredElement` rejects an integer property, data
     *   requirement or slot key outright, and PHP converts such a string key to an integer, so publishing one
     *   would advertise a key the storage model forbids;
     * - a token naming a declared non-primitive property, which would claim storage where none exists (the
     *   reference property is filled by the pipeline, never stored). A token naming a declared PRIMITIVE needs
     *   no rejection here: the property tier overwrites it.
     *
     * @param array<string, PropertySpecification> $properties
     *
     * @phpstan-assert-if-true non-empty-string $token
     */
    private function namesStoredKey(array $properties, mixed $token): bool
    {
        if (!\is_string($token) || $token === '' || $token === (string) (int) $token) {
            return false;
        }

        $declared = $properties[$token] ?? null;

        return $declared === null || $declared->type()->isPrimitive();
    }
}
