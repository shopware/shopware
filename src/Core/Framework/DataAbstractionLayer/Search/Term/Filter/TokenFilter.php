<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type FilterConfig array{excluded_terms: list<string>, min_search_length: int}
 */
#[Package('core')]
class TokenFilter extends AbstractTokenFilter
{
    private const DEFAULT_MIN_SEARCH_TERM_LENGTH = 2;

    /**
     * @var array<string, FilterConfig>
     */
    private array $config = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractTokenFilter
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    public function filter(array $tokens, Context $context): array
    {
        if (empty($tokens)) {
            return $tokens;
        }

        $config = $this->getConfig($context->getLanguageId());

        if ($config === null) {
            return $tokens;
        }
        $tokens = $this->searchTermLengthFilter($tokens, $config['min_search_length']);

        return $this->excludedTermsFilter($tokens, $config['excluded_terms']);
    }

    public function reset(): void
    {
        $this->config = [];
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $excludedTerms
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

    /**
     * @return FilterConfig|null
     */
    private function getConfig(string $languageId): ?array
    {
        if (isset($this->config[$languageId])) {
            return $this->config[$languageId];
        }

        $config = $this->connection->fetchAssociative('
            SELECT
                LOWER(`excluded_terms`) as `excluded_terms`,
                `min_search_length`
            FROM product_search_config
            WHERE language_id = :languageId
            LIMIT 1
        ', ['languageId' => Uuid::fromHexToBytes($languageId)]);

        if (empty($config)) {
            return null;
        }

        return $this->config[$languageId] = [
            'excluded_terms' => \is_string($config['excluded_terms']) ? array_flip(json_decode($config['excluded_terms'], true, 512, \JSON_THROW_ON_ERROR)) : [],
            'min_search_length' => (int) ($config['min_search_length'] ?? self::DEFAULT_MIN_SEARCH_TERM_LENGTH),
        ];
    }
}
