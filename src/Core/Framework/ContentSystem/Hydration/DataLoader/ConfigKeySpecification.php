<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * One declared config key of a {@see LoaderConfigSpecification}. Presence is modeled explicitly
 * (`hasDefault`) so "no default" is distinct from "default is null".
 *
 * @internal
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
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        public string $name,
        public ConfigKeyKind $kind,
        public string $type,             // one of self::TYPES
        public bool $required,           // required means: no default AND the loader cannot produce without it
        public bool $hasDefault = false, // presence modeled explicitly: "no default" is distinct from "defaults to null"
        public mixed $default = null,    // meaningful only when hasDefault is true
        public ?array $adminUI = null,   // same hint shape as element-type property adminUI
    ) {
    }
}
