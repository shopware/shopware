<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingRejection;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingRuntimeResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves one `{{map:path}}` token against its catalogued source and renders it as text.
 *
 * Every admitted candidate resolves through the provider that offered it, using the same route as whole-field mapping.
 *
 * An unresolved result is distinct from text:
 *
 * - **Unavailable path or value** — answers null, so the render caller removes only the token and retains
 *   surrounding authored text.
 * - **Resolved value** — answers escaped text, including an empty string when that is the actual value.
 *
 * ESCAPING IS NOT THE BOUNDARY ON EVERY RENDER PATH, and it cannot be. `Util\HtmlSanitizer::sanitize()` opens with
 * `htmlspecialchars_decode()` to undo double encoding, so a property a template renders through `sw_sanitize` — which
 * `Sw:Content:Text` does, being declared sanitized rich text — sees this escaping undone and the value's own markup
 * purified as markup. That is the same treatment the authored text beside it gets, and the same one a whole-field
 * mapping of the value gets, so the sanitizer is the boundary there and this escaping is defence for the properties
 * that have no sanitizer in front of them.
 *
 * The live catalogue lookup is the render-time access boundary. A withdrawn path returns null even when an older
 * stored layout still contains its token; the write gate and diagnostics separately report authoring faults.
 *
 * @internal
 */
#[Package('framework')]
final class InlineMappingInterpolator
{
    /**
     * The value types interpolation can write into text. A `Struct` or a `Collection` has no faithful text form, so
     * a candidate advertising one is refused by the gate and treated as unavailable here.
     *
     * PUBLIC because `Mapping\StoredMappingInspector` reads it to decide what to admit. One definition, so the set
     * the gate accepts is by construction the set this class can render — two copies could drift apart.
     *
     * A DATE is excluded on purpose, even though it is one cast away from a string. Rendering one in prose needs the
     * locale and timezone formatting to read as anything but machine output. A provider wanting to expose an
     * inline date must format it and advertise `string`.
     */
    public const STRINGIFIABLE_TYPES = PropertyType::PRIMITIVE_TYPES;

    public function __construct(
        private readonly MappingRuntimeResolver $mappingResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $ambient root-ambient values keyed by page-level data requirement key
     * @param array<string, mixed> $loaderValues resolved values for this element
     *
     * @return string|null the replacement text, or null when the value is unavailable
     */
    public function interpolate(
        string $path,
        array $ambient,
        string $rootSource,
        StoredElement $element,
        ?RenderingCacheContext $cacheContext = null,
        array $loaderValues = [],
        ?InlineMappingExpansionBudget $budget = null,
    ): ?string {
        $resolved = $this->mappingResolver->resolveInline(
            $rootSource,
            $path,
            new MappingSourceResolutionContext($element, $ambient, $loaderValues, $cacheContext),
        );
        if ($resolved instanceof MappingRejection || !$resolved->hasValue) {
            return null;
        }

        $text = $this->stringify($resolved->value);

        if ($text === null) {
            return null;
        }

        $budget ??= new InlineMappingExpansionBudget();
        // Reject a large source before escaping can allocate up to six times its size. The escaped result
        // is checked too, so entity expansion cannot bypass either output allowance.
        $budget->assertFits(\strlen($text));
        $escaped = htmlspecialchars($text, \ENT_QUOTES, 'UTF-8');
        $budget->assertFits(\strlen($escaped));

        return $escaped;
    }

    /**
     * The candidate already promised a primitive, so this normally just casts. It answers null for anything else
     * because a promise is not a guarantee: stored data can outlive the code that shaped it, and a provider
     * returning the wrong type must make the value unavailable rather than raise a cast error mid-page.
     *
     * A bool becomes `1`/`0` rather than `true`/`false` to match PHP's own string cast, which is what an author
     * moving a value between an inline token and a whole-field mapping will have seen.
     */
    private function stringify(mixed $value): ?string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
