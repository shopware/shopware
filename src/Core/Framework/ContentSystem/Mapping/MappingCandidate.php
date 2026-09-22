<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\Log\Package;

/**
 * One offer in the data-mapping catalogue: a path into a layout's root-ambient data that an author may bind a
 * mappable element property to, described in the terms the Administration needs to present it.
 *
 * The catalogue is curated rather than derived from the entity definitions, and that is a correctness and a
 * security decision rather than a convenience. Two constraints a derived catalogue could not honour:
 *
 * - {@see ContextPathResolver} traverses `Struct::getVars()` and normally needs a `Struct` at every
 *   intermediate step. Catalogued mappings have one narrow exception for a terminal lookup in the root
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
 * `$path` MUST be dotted, and the constructor enforces it: mapping exposes selected members of root-ambient
 * data, while consuming an ambient value itself remains ordinary context wiring.
 */
#[Package('framework')]
final readonly class MappingCandidate
{
    /**
     * @param string $path the dotted path into the root-ambient data, including its leading context key
     *                     (`category.name`); the segment before the first dot names the page-level data
     *                     requirement the value is resolved against
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
    ) {
        if (!str_contains($path, '.')) {
            throw ContentSystemException::invalidMappingCandidatePath($path);
        }
    }

    /**
     * @return array{path: string, label: string, description: string, group: string, valueType: string, contextType: string, projection: string|null, labelTranslations: object, descriptionTranslations: object}
     */
    public function toSchema(): array
    {
        return [
            'path' => $this->path,
            'label' => $this->label,
            'description' => $this->description,
            'group' => $this->group,
            'valueType' => $this->valueType,
            'contextType' => $this->contextType->value,
            'projection' => $this->projection,
            'labelTranslations' => (object) $this->labelTranslations,
            'descriptionTranslations' => (object) $this->descriptionTranslations,
        ];
    }
}
