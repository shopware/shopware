<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class SearchFieldConfig
{
    public function __construct(
        private readonly string $field,
        private float $ranking,
        private readonly bool $tokenize,
        private readonly bool $andLogic = false,
        private readonly bool $prefixMatch = true,
        private readonly bool $useExactSubfield = false,
        private readonly bool $phrase = false,
    ) {
    }

    public function tokenize(): bool
    {
        return $this->tokenize;
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function isCustomField(): bool
    {
        return str_contains($this->field, 'customFields');
    }

    public function isAndLogic(): bool
    {
        return $this->andLogic;
    }

    public function setRanking(float $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function usePrefixMatch(): bool
    {
        return $this->prefixMatch;
    }

    public function useExactSubfield(): bool
    {
        return $this->useExactSubfield;
    }

    /**
     * A phrase config produces only the multi-word proximity clause (match_phrase_prefix),
     * not the per-token exact/fuzzy/prefix/ngram set. Used to add an explicit phrase boost
     * on top of the per-token queries without re-running the full builder.
     */
    public function isPhrase(): bool
    {
        return $this->phrase;
    }

    public function withPhrase(): self
    {
        return $this->with(phrase: true);
    }

    /**
     * Returns a clone with n-gram matching disabled ({@see tokenize()} gates the n-gram clause).
     * Used to keep substring matching off for OR multi-word searches, where a query word matching
     * inside an unrelated word (e.g. "line" in "Portaline") is noise rather than a useful hit.
     */
    public function withoutNgram(): self
    {
        return $this->with(tokenize: false);
    }

    public function getFuzziness(string $token): string|int
    {
        // Disable fuzziness for numeric tokens or a serial of at least 3 digits
        if (is_numeric($token) || preg_match('/\d{3,}/', $token)) {
            return 0;
        }

        // (SKU-ish strings, e.g. "SD345-XYZ") - require exact match
        if (preg_match('/[A-Za-z].*\d|\d.*[A-Za-z]/', $token)) {
            return 0;
        }

        // Let AUTO:5,10 handle length thresholds (0 for <5, 1 for 5–9, 2 for ≥10)
        return 'AUTO:5,10';
    }

    /**
     * Lock a longer exact prefix on longer tokens so fuzzy expansion stays proportional:
     * short tokens already allow few edits (AUTO:5,10) so lock 2 chars; long tokens carry
     * more signal and can afford a 3-char lock without losing useful matches.
     */
    public function getPrefixLength(string $token): int
    {
        return mb_strlen($token) >= 10 ? 3 : 2;
    }

    /**
     * Single clone point: a new constructor field only has to be threaded through here once,
     * rather than duplicated across every with*() method.
     */
    private function with(?bool $tokenize = null, ?bool $phrase = null): self
    {
        return new self(
            $this->field,
            $this->ranking,
            $tokenize ?? $this->tokenize,
            $this->andLogic,
            $this->prefixMatch,
            $this->useExactSubfield,
            $phrase ?? $this->phrase,
        );
    }
}
