<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * One offer in the data-mapping catalogue: a typed source reference that an author may bind a mappable
 * element property to, described in the terms the Administration needs to present it.
 *
 * Root candidates are curated rather than derived from entity definitions, and that is a correctness and a
 * security decision rather than a convenience. Two constraints a derived catalogue could not honour:
 *
 * - {@see ContextPathResolver} traverses `Struct::getVars()` and normally needs a `Struct` at every
 *   intermediate step. Catalogued root mappings have one narrow exception for a terminal lookup in the root
 *   entity's array-backed `customFields` member. Only a path a provider has vouched for is offered.
 * - The framework's protection gate (ApiAware, `isProtected`, the customFields blocklist) is applied by
 *   `StructEncoder` to `Struct` leaves only, so a mapped SCALAR leaf is served unfiltered. The catalogue is
 *   the allowlist that keeps an author from reaching one.
 *
 * `$valueType` is the EFFECTIVE type — what the property receives after `$projection` has run, not what sits
 * at `$path`. A candidate therefore advertises "Category image → MediaEntity" and keeps the adapter that gets
 * it there to itself, which is what lets the Administration filter candidates by a plain type check and keeps
 * projections out of the authoring UI entirely.
 *
 * `$path` is the stable candidate key used by inline text mappings. Field mappings persist `$source`, which can
 * identify a whole value or a selected member within a typed source.
 */
#[Package('framework')]
final readonly class MappingCandidate
{
    public MappingSourceReference $source;

    /**
     * @param string $path stable candidate key, currently a dotted root-member path for root candidates (`category.name`)
     * @param string $label snippet key for the human-readable name, resolved by the Administration
     * @param string $description snippet key for the supporting line under the name
     * @param string $group grouping id the Administration renders as a section, e.g. `basic` or `media`
     * @param string $valueType the effective type: a `PropertyType::PRIMITIVE_TYPES` member, or an FQCN
     * @param string|null $projection name of the projection applied to the resolved value, or null when the
     *                                value at `$path` is already of `$valueType`
     * @param array<string, string> $labelTranslations literal localized labels, preferred over the snippet key
     * @param array<string, string> $descriptionTranslations literal localized descriptions, preferred over the snippet key
     */
    public function __construct(
        public string $path,
        public string $label,
        public string $description,
        public string $group,
        public string $valueType,
        public ContextType $contextType = ContextType::Single,
        public ?string $projection = null,
        public array $labelTranslations = [],
        public array $descriptionTranslations = [],
        ?MappingSourceReference $source = null,
    ) {
        if ($path === '' || ($source === null && !str_contains($path, '.'))) {
            throw ContentSystemException::invalidMappingCandidatePath($path);
        }

        $this->source = $source ?? MappingSourceReference::fromRootPath($path);
    }

    /**
     * @return array{path: string, label: string, description: string, group: string, valueType: string, compatibleTypes: list<string>, contextType: string, projection: string|null, source: array{type: string, id: string, config?: array<string, mixed>, path?: string}, labelTranslations: object, descriptionTranslations: object}
     */
    public function toSchema(): array
    {
        return [
            'path' => $this->path,
            'source' => $this->source->jsonSerialize(),
            'label' => $this->label,
            'description' => $this->description,
            'group' => $this->group,
            'valueType' => $this->valueType,
            'compatibleTypes' => $this->compatibleTypes(),
            'contextType' => $this->contextType->value,
            'projection' => $this->projection,
            'labelTranslations' => (object) $this->labelTranslations,
            'descriptionTranslations' => (object) $this->descriptionTranslations,
        ];
    }

    /**
     * @return list<string> declared PHP class or interface types that accept this candidate's effective value
     */
    private function compatibleTypes(): array
    {
        if (
            \in_array($this->valueType, PropertyType::PRIMITIVE_TYPES, true)
            || $this->valueType === 'object'
        ) {
            return [];
        }

        $types = [$this->valueType];

        if (class_exists($this->valueType)) {
            $types = [
                ...$types,
                ...array_values(class_parents($this->valueType) ?: []),
                ...array_values(class_implements($this->valueType) ?: []),
            ];
        } elseif (interface_exists($this->valueType)) {
            $types = [
                ...$types,
                ...array_values(class_implements($this->valueType) ?: []),
            ];
        }

        return array_values(array_unique($types));
    }
}
