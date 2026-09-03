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
    public const REFERENCED_TYPES = ['string', 'list<string>', 'object'];

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
}
