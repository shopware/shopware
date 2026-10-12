<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * One declared config key of a {@see LoaderConfigSpecification}. Presence is modeled explicitly
 * (`hasDefault`) so "no default" is distinct from "default is null".
 */
#[Package('framework')]
final readonly class ConfigKeySpecification
{
    /**
     * The closed set of declarable value types. ContentSystemDataLoaderCompilerPass fails the container
     * build on any other value.
     */
    public const TYPES = ['string', 'integer', 'number', 'boolean', 'list<string>', 'map'];

    /**
     * The closed set of declarable referenced-value types. ContentSystemDataLoaderCompilerPass fails the
     * container build on any other value.
     */
    public const REFERENCED_TYPES = ['string', 'list<string>'];

    /**
     * `$type` is the type of the reference token, `$referencedType` the type of the value it points at.
     *
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        public string $name,
        public ConfigKeyKind $kind,
        public string $type,             // one of self::TYPES
        public bool $required,           // required means: no default AND the loader cannot produce without it
        public bool $hasDefault = false,
        public mixed $default = null,    // meaningful only when hasDefault is true
        public ?array $adminUI = null,   // same hint shape as element-type property adminUI
        public string $referencedType = 'string',  // one of self::REFERENCED_TYPES; meaningful only on ConfigKeyKind::PropertyReference
        public ?string $mergesInto = null,         // name of another declared key this key's resolved list is unioned into
    ) {
    }

    /**
     * Whether a dereferenced stored value matches `$referencedType`: a string for `string`, a list of strings for
     * `list<string>`. The one statement of that match: {@see LoaderInputResolver} hands the loader only a value
     * this admits, and the diagnostics count a required input as filled only on the same answer.
     */
    public function admitsReferencedValue(mixed $value): bool
    {
        return match ($this->referencedType) {
            'string' => \is_string($value),
            'list<string>' => \is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value,
            default => false,
        };
    }

    /**
     * Whether a property declared as the lone primitive `$primitive` can hold a value {@see admitsReferencedValue()}
     * admits. A lone primitive holds one scalar (a translatable one, one scalar per language) and never a list, so
     * a `string` declaration qualifies for `string` and no declaration qualifies for `list<string>`.
     */
    public function admitsDeclaredPrimitive(string $primitive): bool
    {
        return match ($this->referencedType) {
            'string' => $primitive === 'string',
            default => false,
        };
    }
}
