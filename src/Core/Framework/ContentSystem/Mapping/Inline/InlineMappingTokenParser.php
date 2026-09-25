<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;

/**
 * The one definition of the inline mapping token syntax.
 *
 * THIS CLASS MUST STAY THE ONLY PLACE THAT KNOWS THE PATTERN. Three layers read it — the render-time expansion,
 * the write gate, and the diagnose routes — and a second copy is a security defect rather than a style problem: if
 * the gate's notion of a token and the expansion's notion ever diverge, a token the gate never judged reaches
 * {@see ContextPathResolver}, which traverses `Struct::getVars()` unfiltered.
 *
 * Three properties of the pattern are deliberate:
 *
 * - **The `map:` prefix** makes a mapping author-declared rather than inferred. Without it the catalogue would be
 *   the only thing separating a mapping from prose, so any dotted brace expression an author typed would be
 *   claimed — and an unknown one could not then be reported as an error, because it might be prose.
 * - **It reserves a namespace** against {@see StoredTreePreparer}'s placeholder substitution, which replaces
 *   `{{<key>}}` for every key in the specification's placeholder values and runs BEFORE expansion.
 *   {@see PlaceholderValues::from()} DROPS a `map:`-prefixed key to keep the two namespaces disjoint by enforcement
 *   rather than by convention. It drops rather than throws because placeholder keys include request query
 *   parameters, so rejecting would let any visitor error a page with a crafted query string.
 * - **The dot is required**, mirroring {@see MappingCandidate}'s constructor. Mapping exposes members of
 *   root-ambient data; consuming an ambient value whole is ordinary context wiring, not a mapping.
 *
 * The path character class admits neither `<`, `>`, `"` nor `'`, so a path cannot carry markup out of a token even
 * before escaping.
 *
 * @internal
 */
#[Package('framework')]
final class InlineMappingTokenParser
{
    /**
     * The reserved placeholder-key prefix. {@see PlaceholderValues::from()} drops keys starting with this.
     */
    public const TOKEN_PREFIX = 'map:';

    private const TOKEN_PATTERN = '/\{\{\s*map:(?<path>[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)\s*\}\}/';

    /**
     * Cheap guard so callers can skip a string without running the full match. Every token contains the prefix,
     * so a string without it cannot hold one.
     */
    public function containsToken(string $text): bool
    {
        return str_contains($text, '{{') && str_contains($text, self::TOKEN_PREFIX);
    }

    /**
     * @return list<InlineMappingToken> in the order they occur
     */
    public function parse(string $text): array
    {
        if (!$this->containsToken($text)) {
            return [];
        }

        if (!preg_match_all(self::TOKEN_PATTERN, $text, $matches, \PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tokens = [];

        foreach ($matches['path'] as $index => $path) {
            $tokens[] = new InlineMappingToken(
                path: $path[0],
                match: $matches[0][$index][0],
                offset: $matches[0][$index][1],
            );
        }

        return $tokens;
    }

    /**
     * Replaces every token whose path `$resolve` answers for.
     *
     * Returning `null` from `$resolve` leaves the token in the text verbatim, which is how an uncatalogued path
     * behaves: it was never a mapping, so it is not this class's business to delete it. Returning a string —
     * including an empty one — replaces the token, which is how a catalogued path that resolved to nothing behaves.
     *
     * @param callable(string): ?string $resolve receives the path, answers the replacement or null
     */
    public function replace(string $text, callable $resolve): string
    {
        if (!$this->containsToken($text)) {
            return $text;
        }

        $replaced = preg_replace_callback(
            self::TOKEN_PATTERN,
            static function (array $match) use ($resolve): string {
                $replacement = $resolve($match['path']);

                return $replacement ?? $match[0];
            },
            $text
        );

        return $replaced ?? $text;
    }

    /**
     * The canonical spelling of a token, which is what any writer should emit. Authored text may carry inner
     * whitespace and {@see replace()} tolerates it, but nothing needs to produce it.
     */
    public function canonicalToken(string $path): string
    {
        return '{{' . self::TOKEN_PREFIX . $path . '}}';
    }

    /**
     * Whether the token at `$offset` sits inside an HTML tag rather than in text content.
     *
     * Escaping a value makes it safe in text context but not in attribute context, where an escaped value can
     * still terminate a quoted attribute in enough browsers to matter and where `sw_sanitize` is not a reliable
     * second line. Supporting attributes properly would mean HTML-aware interpolation; refusing them at the gate
     * costs one scan.
     *
     * The test is the last unbalanced angle bracket before the token: a `<` with no `>` after it means the offset
     * is still inside that tag.
     */
    public function occursInsideMarkup(string $text, int $offset): bool
    {
        $before = substr($text, 0, $offset);

        $lastOpen = strrpos($before, '<');

        if ($lastOpen === false) {
            return false;
        }

        $lastClose = strrpos($before, '>');

        return $lastClose === false || $lastClose < $lastOpen;
    }
}
