<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;

/**
 * One offer in the data-mapping catalogue: a typed source reference that an author may bind a mappable
 * element property to, described in the terms the Administration needs to present it.
 *
 * Root candidates are curated rather than derived from entity definitions, and that is a correctness and a
 * security decision rather than a convenience. Two constraints a derived catalogue could not honour:
 *
 * - The default root reader uses {@see ContextPathResolver} over `Struct` values, with a narrow terminal
 *   lookup into array-backed `customFields`. Providers can override that read with explicit storefront-facing
 *   accessors for translated or inherited values. Only a path a provider has vouched for is offered.
 * - The framework's protection gate (ApiAware, `isProtected`, the customFields blocklist) is applied by
 *   `StructEncoder` to `Struct` leaves only, so a mapped SCALAR leaf is served unfiltered. The catalogue is
 *   the allowlist that keeps an author from reaching one.
 *
 * `$valueType` is the effective type delivered by the provider. A provider that reads an assignment or
 * another intermediate value reshapes it before returning it to the property.
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
     * @param array<string, string> $labelTranslations literal localized labels, preferred over the snippet key
     * @param array<string, string> $descriptionTranslations literal localized descriptions, preferred over the snippet key
     * @param list<string> $cacheTags metadata tags to collect only when this candidate is consulted during rendering
     */
    public function __construct(
        public string $path,
        public string $label,
        public string $description,
        public string $group,
        public string $valueType,
        public ContextType $contextType = ContextType::Single,
        public array $labelTranslations = [],
        public array $descriptionTranslations = [],
        ?MappingSourceReference $source = null,
        public array $cacheTags = [],
    ) {
        if ($path === '' || ($source === null && !str_contains($path, '.'))) {
            throw ContentSystemException::invalidMappingCandidatePath($path);
        }

        $this->source = $source ?? MappingSourceReference::fromRootPath($path);

        if ($path !== $this->source->displayName()) {
            throw ContentSystemException::mappingCandidatePathMismatch($path, $this->source->displayName());
        }
    }

    /**
     * @return array{path: string, label: string, description: string, group: string, valueType: string, compatibleTypes: list<string>, inlineMappable: bool, contextType: string, source: array{type: string, id: string, config?: array<string, mixed>, path?: string}, labelTranslations: object, descriptionTranslations: object}
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
            'compatibleTypes' => MappingTypeCompatibility::compatibleTypes($this->valueType),
            'inlineMappable' => \in_array($this->valueType, PropertyType::PRIMITIVE_TYPES, true)
                && InlineMappingTokenParser::isValidPath($this->path),
            'contextType' => $this->contextType->value,
            'labelTranslations' => (object) $this->labelTranslations,
            'descriptionTranslations' => (object) $this->descriptionTranslations,
        ];
    }
}
