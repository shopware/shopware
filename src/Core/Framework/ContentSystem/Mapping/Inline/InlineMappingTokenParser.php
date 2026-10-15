<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
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
 * - **Preparation captures spans before substitution.** Placeholder values can contain the same characters as a
 *   mapping token, but only authored spans are carried into dependency discovery and expansion.
 * - **The dot is required**, mirroring {@see MappingCandidate}'s constructor. Mapping exposes members of a typed
 *   source; consuming a source whole is ordinary context wiring, not a mapping.
 *
 * The path character class admits neither `<`, `>`, `"` nor `'`, so a path cannot carry markup out of a token even
 * before escaping.
 *
 * @internal
 */
#[Package('framework')]
final class InlineMappingTokenParser
{
    public const TOKEN_PREFIX = 'map:';

    /**
     * Maximum authored text size the parser will scan when it contains a mapping token.
     */
    public const MAX_INPUT_BYTES = 262_144;

    private const TOKEN_PATTERN = '/\{\{\s*map:(?<path>(?:[A-Za-z0-9_-]+:[A-Za-z0-9_-]+|[A-Za-z0-9_]+)(?:\.[A-Za-z0-9_\x7f-\xff]+)+)\s*\}\}/';

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

        if (\strlen($text) > self::MAX_INPUT_BYTES) {
            throw ContentSystemException::inlineMappingInputTooLarge(self::MAX_INPUT_BYTES);
        }

        $tokens = [];
        $cursor = 0;
        $insideTag = false;
        $quote = null;

        while (preg_match(self::TOKEN_PATTERN, $text, $matches, \PREG_OFFSET_CAPTURE, $cursor) === 1) {
            $offset = $matches[0][1];
            [$insideTag, $quote] = $this->scanMarkupState($text, $cursor, $offset, $insideTag, $quote);
            $tokens[] = new InlineMappingToken(
                path: $matches['path'][0],
                match: $matches[0][0],
                offset: $offset,
                insideMarkup: $insideTag,
            );
            $cursor = $offset + \strlen($matches[0][0]);
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

        return $this->replaceTokens($text, $this->parse($text), $resolve);
    }

    /**
     * Replaces tokens using spans captured before placeholder substitution. The caller owns those spans and must
     * preserve their offsets while rewriting the surrounding literal text.
     *
     * @param list<InlineMappingToken> $tokens
     * @param callable(string): ?string $resolve
     */
    public function replaceTokens(string $text, array $tokens, callable $resolve): string
    {
        if ($tokens === []) {
            return $text;
        }

        $result = '';
        $cursor = 0;

        foreach ($tokens as $token) {
            $result .= substr($text, $cursor, $token->offset - $cursor);
            $result .= $token->insideMarkup ? $token->match : ($resolve($token->path) ?? $token->match);
            $cursor = $token->offset + \strlen($token->match);
        }

        return $result . substr($text, $cursor);
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
     * A quoted `>` does not close a tag. Parse uses the same scanner once across all token offsets.
     */
    public function occursInsideMarkup(string $text, int $offset): bool
    {
        return $this->scanMarkupState($text, 0, $offset, false, null)[0];
    }

    /**
     * @return array{bool, ?string}
     */
    private function scanMarkupState(string $text, int $start, int $end, bool $insideTag, ?string $quote): array
    {
        for ($i = $start; $i < $end; ++$i) {
            $character = $text[$i];

            if (!$insideTag) {
                if ($character === '<') {
                    $insideTag = true;
                }

                continue;
            }

            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === '\'') {
                $quote = $character;
            } elseif ($character === '>') {
                $insideTag = false;
            }
        }

        return [$insideTag, $quote];
    }
}
