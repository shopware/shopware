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
 * - **A mapping marker is captured even when its path is malformed.** The path grammar still requires a member
 *   below its source, even though a typed {@see MappingCandidate} may expose a whole source for field mappings.
 *   Capturing malformed mapping intent lets the write gate diagnose it and the renderer remove it rather than
 *   expose the raw token.
 *
 * The valid path grammar admits neither `<`, `>`, `"` nor `'`. Malformed mapping markers are removed before
 * interpolation, so they cannot carry markup into the output either.
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

    private const TOKEN_PATTERN = '/\{\{\s*map:(?<path>[^{}]*?)\s*\}\}/';

    private const VALID_PATH_PATTERN = '/^(?:[A-Za-z0-9_-]+:[A-Za-z0-9_-]+|[A-Za-z0-9_]+)(?:\.[A-Za-z0-9_\x7f-\xff]+)+$/';

    /**
     * Cheap guard so callers can skip a string without running the full match. Every token contains the prefix,
     * so a string without it cannot hold one.
     */
    public function containsToken(string $text): bool
    {
        return str_contains($text, '{{') && str_contains($text, self::TOKEN_PREFIX);
    }

    /**
     * Whether a catalogue path can be represented by a valid inline mapping token.
     */
    public static function isValidPath(string $path): bool
    {
        return preg_match(self::VALID_PATH_PATTERN, $path) === 1;
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
            $path = $matches['path'][0];
            $tokens[] = new InlineMappingToken(
                path: $path,
                match: $matches[0][0],
                offset: $offset,
                insideMarkup: $insideTag,
                malformed: !self::isValidPath($path),
            );
            $cursor = $offset + \strlen($matches[0][0]);
        }

        return $tokens;
    }

    /**
     * Replaces tokens using spans captured before placeholder substitution. The caller owns those spans and must
     * preserve their offsets while rewriting the surrounding literal text. A null resolver result leaves a valid
     * token verbatim; an empty string removes it. Malformed tokens are removed without calling the resolver.
     *
     * @param list<InlineMappingToken> $tokens
     * @param callable(string): ?string $resolve receives the path, answers the replacement or null
     */
    public function replaceTokens(string $text, array $tokens, callable $resolve, ?InlineMappingExpansionBudget $budget = null): string
    {
        if ($tokens === []) {
            $budget?->consume(\strlen($text));

            return $text;
        }

        $result = '';
        $cursor = 0;

        foreach ($tokens as $token) {
            $literalBytes = $token->offset - $cursor;
            $budget?->consume($literalBytes);
            $result .= substr($text, $cursor, $literalBytes);

            // Do not invoke another provider once the output allowance has been consumed.
            if (!$token->malformed && !$token->insideMarkup) {
                $budget?->assertFits(1);
            }

            $replacement = $token->malformed
                ? ''
                : ($token->insideMarkup ? $token->match : ($resolve($token->path) ?? $token->match));
            $budget?->consume(\strlen($replacement));
            $result .= $replacement;
            $cursor = $token->offset + \strlen($token->match);
        }

        $budget?->consume(\strlen($text) - $cursor);

        return $result . substr($text, $cursor);
    }

    /**
     * The canonical spelling of a token, which is what any writer should emit. Authored text may carry inner
     * whitespace and {@see parse()} tolerates it, but nothing needs to produce it.
     */
    public function canonicalToken(string $path): string
    {
        return '{{' . self::TOKEN_PREFIX . $path . '}}';
    }

    /**
     * Tracks whether parsed spans sit inside markup. A quoted `>` does not close a tag; parse carries the scanner
     * state between spans rather than rescanning from the start. Tokens in attributes remain literal because
     * escaping their replacements as text would not make attribute interpolation safe.
     *
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
