<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

class TokenFilter extends AbstractTokenFilter
{
    private const DEFAULT_MIN_SEARCH_TERM_LENGTH = 2;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getDecorated(): AbstractTokenFilter
    {
        throw new DecorationPatternException(self::class);
    }

    public function filter(array $tokens, Context $context): array
    {
        if (empty($tokens)) {
            return $tokens;
        }

        $config = $this->getConfig($context->getLanguageId());

        if (empty($config)) {
            return $tokens;
        }
        $tokens = $this->searchTermLengthFilter($tokens, $config['min_search_length']);
        $tokens = $this->excludedTermsFilter($tokens, $config['excluded_terms']);

        return $tokens;
    }

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

    private function getConfig(string $languageId): array
    {
        if (isset($this->config[$languageId])) {
            return $this->config[$languageId];
        }

        $config = $this->connection->fetchAssoc('
            SELECT
                `excluded_terms`,
                `min_search_length`
            FROM product_search_config
            WHERE language_id = :languageId
            LIMIT 1
        ', ['languageId' => Uuid::fromHexToBytes($languageId)]);

        if (empty($config)) {
            return [];
        }

        return $this->config[$languageId] = [
            'excluded_terms' => \is_string($config['excluded_terms']) ? array_flip(json_decode($config['excluded_terms'], true)) : [],
            'min_search_length' => (int) ($config['min_search_length'] ?? self::DEFAULT_MIN_SEARCH_TERM_LENGTH),
        ];
    }
}
