<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Tokenizer used by the Elasticsearch search path.
 *
 * Unlike {@see Tokenizer}, this implementation does
 * not strip non-alphanumeric characters or honour `shopware.search.preserved_chars`. Separator handling
 * (commas, slashes, hyphens, periods, letter↔digit boundaries) is the responsibility of the
 * Elasticsearch analyzer chain — `word_delimiter_graph`, `sw_whitespace_analyzer` and friends — so
 * preserving the term verbatim and letting the analyzer split it produces strictly better matches
 * for technical strings like `5,5`, `M8x20` or `HSS-G`.
 *
 * Tokens that contain no letters or digits at all (e.g. `&%$`, `---`) are still rejected here so a
 * pure-punctuation request does not reach Elasticsearch.
 */
#[Package('framework')]
final class ElasticsearchTokenizer implements TokenizerInterface
{
    /**
     * @return list<string>
     */
    public function tokenize(string $string, ?int $tokenMinimumLength = null): array
    {
        $tokenMinimumLength ??= AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;

        $tokens = preg_split('/\s+/u', mb_strtolower($string), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= $tokenMinimumLength
                && preg_match('/[\p{L}\p{N}]/u', $token) === 1,
        );

        return array_values(array_unique($tokens));
    }
}
