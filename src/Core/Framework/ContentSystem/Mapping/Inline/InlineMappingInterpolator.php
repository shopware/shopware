<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Resolves one `{{map:path}}` token against a layout's root-ambient data and renders it as text.
 *
 * The steps mirror `Rendering/ContextDeliveryResolver::ambientValueFor()`, deliberately: an inline token and a
 * whole-field mapping read the same catalogue through the same path resolver and apply the same projections, and the
 * two must not drift on what a path means.
 *
 * Three outcomes, and keeping them apart is the point:
 *
 * - **Uncatalogued path** — answers null, which leaves the token verbatim in the text. The token was never a
 *   mapping this system offers, so deleting the author's characters would be presumptuous, and leaving them makes a
 *   typo visible in the preview instead of silently blank. Nothing is read, so nothing can leak.
 * - **Catalogued, resolved to nothing** — answers `''`. A mapping ran and found no value. Unlike a whole-field
 *   mapping there is no authored value to fall back to, because the surrounding text IS the authored value.
 * - **Catalogued, resolved** — answers the escaped text.
 *
 * ESCAPING IS NOT THE BOUNDARY ON EVERY RENDER PATH, and it cannot be. `Util\HtmlSanitizer::sanitize()` opens with
 * `htmlspecialchars_decode()` to undo double encoding, so a property a template renders through `sw_sanitize` — which
 * `Sw:Content:Text` does, being declared sanitized rich text — sees this escaping undone and the value's own markup
 * purified as markup. That is the same treatment the authored text beside it gets, and the same one a whole-field
 * mapping of the value gets, so the sanitizer is the boundary there and this escaping is defence for the properties
 * that have no sanitizer in front of them.
 *
 * NOTE ON THE CATALOGUE LOOKUP. It is here for correctness, not as the security boundary: the write gate and
 * `DraftLayoutChecker` are what refuse an uncatalogued token before it can render. A tree that reached this class
 * with one — written by a migration or raw SQL, which bypass the write boundary — is left alone rather than
 * resolved, so the lookup fails closed either way.
 *
 * @internal
 */
#[Package('framework')]
final class InlineMappingInterpolator
{
    /**
     * The value types interpolation can write into text. A `Struct` or a `Collection` has no faithful text form, so
     * a candidate advertising one is refused by the gate and rendered empty here.
     *
     * PUBLIC because `Mapping\StoredMappingInspector` reads it to decide what to admit. One definition, so the set
     * the gate accepts is by construction the set this class can render — two copies would be free to drift into a
     * token that passes validation and then renders empty.
     *
     * A DATE is excluded on purpose, even though it is one cast away from a string. Rendering one in prose needs the
     * locale and timezone of the sales-channel context to read as anything but machine output, and this class has
     * neither — it runs per token with nothing but the ambient data. A candidate wanting an inline date should
     * declare a projection that formats it and advertise `string`, which is what projections are for.
     */
    public const STRINGIFIABLE_TYPES = PropertyType::PRIMITIVE_TYPES;

    public function __construct(
        private readonly AbstractContentSystemMappingCandidateRegistry $candidateRegistry,
        private readonly AbstractContentSystemPropertyProjectionRegistry $projectionRegistry,
        private readonly ContextPathResolver $pathResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $ambient root-ambient values keyed by page-level data requirement key
     *
     * @return string|null the replacement text, or null when the path is not catalogued and the token must stay
     */
    public function interpolate(string $path, array $ambient, string $rootSource, string $elementId): ?string
    {
        $candidate = $this->candidateRegistry->forRootSource($rootSource)[$path] ?? null;

        if ($candidate === null) {
            return null;
        }

        if (!\in_array($candidate->valueType, self::STRINGIFIABLE_TYPES, true)) {
            return '';
        }

        $resolved = $this->resolve($candidate, $ambient, $elementId);

        if ($resolved === null) {
            return '';
        }

        $text = $this->stringify($resolved);

        if ($text === null) {
            return '';
        }

        return htmlspecialchars($text, \ENT_QUOTES, 'UTF-8');
    }

    /**
     * Walks the candidate's path into the ambient value the first segment names, then applies the candidate's
     * projection if it declares one.
     *
     * Everything is resolved as OPTIONAL (`required: false`), so an untraversable path yields null instead of
     * throwing mid-render. An inline token is one character among many in a paragraph; it must not be able to take
     * a storefront page down.
     *
     * @param array<string, mixed> $ambient
     */
    private function resolve(MappingCandidate $candidate, array $ambient, string $elementId): mixed
    {
        $segments = $this->pathResolver->parseContextKey($candidate->path);
        $ambientKey = explode('.', $candidate->path)[0];

        if (!\array_key_exists($ambientKey, $ambient)) {
            return null;
        }

        $data = $ambient[$ambientKey];

        if (!$data instanceof Struct) {
            return null;
        }

        $resolved = $this->pathResolver->resolveMappingPath(
            $data,
            $segments,
            false,
            $candidate->path,
            $elementId
        );

        if ($resolved === null || $candidate->projection === null) {
            return $resolved;
        }

        return $this->project($candidate->projection, $resolved);
    }

    /**
     * A projection that cannot run yields null, which renders empty rather than failing the page — the same rule
     * whole-field mapping follows. Two things can be wrong and both mean stored data outlived its code: the
     * projection is gone because the plugin registering it was removed, or the value is not the type it declared.
     */
    private function project(string $projection, mixed $value): mixed
    {
        $service = $this->projectionRegistry->get($projection);

        if ($service === null || !$this->admitsInput($service->inputType(), $value)) {
            return null;
        }

        return $service->project($value);
    }

    /**
     * A projection declares its input type as a contract and is entitled to assume it, so the value is checked
     * before the call rather than left to fail inside one. `inputType()` is either an FQCN or a
     * `PropertyType::PRIMITIVE_TYPES` member, which the container build already guaranteed.
     */
    private function admitsInput(string $inputType, mixed $value): bool
    {
        return match ($inputType) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            // `number` admits an int too: an integral value read off an entity is a valid float input, and PHP
            // widens it on the call anyway.
            'number' => \is_float($value) || \is_int($value),
            'boolean' => \is_bool($value),
            default => $value instanceof $inputType,
        };
    }

    /**
     * The candidate already promised a primitive, so this normally just casts. It answers null for anything else
     * because a promise is not a guarantee: stored data can outlive the code that shaped it, and a projection
     * returning the wrong type must render empty rather than raise a cast error mid-page.
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
