<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
class TokenFilter extends AbstractTokenFilter
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SearchConfigLoader $configLoader,
    ) {
    }

    public function getDecorated(): AbstractTokenFilter
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * {@inheritdoc}
     */
    public function filter(array $tokens, Context $context): array
    {
        if (empty($tokens)) {
            return $tokens;
        }

        $config = $this->configLoader->load($context);

        $minSearchLength = $config[0]['min_search_length'] ?? AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;

        $tokens = $this->searchTermLengthFilter($tokens, $minSearchLength);

        return $this->excludedTermsFilter(
            $tokens,
            array_flip($config[0]['excluded_terms'] ?? [])
        );
    }

    public function reset(): void
    {
        // do nothing
    }

    /**
     * @param list<string> $tokens
     * @param array<string> $excludedTerms
     *
     * @return list<string>
     */
    private function excludedTermsFilter(array $tokens, array $excludedTerms): array
    {
        if (empty($excludedTerms) || empty($tokens)) {
            return $tokens;
        }

        $filtered = [];
        foreach ($tokens as $token) {
            if (!isset($excludedTerms[$token])) {
                $filtered[] = $token;
            }
        }

        return $filtered;
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function searchTermLengthFilter(array $tokens, int $minSearchTermLength): array
    {
        $filtered = [];
        foreach ($tokens as $tag) {
            $tag = trim((string) $tag);

            if (empty($tag) || mb_strlen($tag) < $minSearchTermLength) {
                continue;
            }

            $filtered[] = $tag;
        }

        return $filtered;
    }
}
