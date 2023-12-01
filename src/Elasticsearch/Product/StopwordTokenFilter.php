<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class StopwordTokenFilter extends AbstractTokenFilter
{
    private const DEFAULT_MIN_SEARCH_TERM_LENGTH = 2;

    /**
     * @var array<string, int>
     */
    private array $config = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection
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

        $minSearchLength = $this->getMinSearchLength($context->getLanguageId());

        if ($minSearchLength === null) {
            return $tokens;
        }

        return $this->searchTermLengthFilter($tokens, $minSearchLength);
    }

    public function reset(): void
    {
        $this->config = [];
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
            $tag = trim($tag);

            if (empty($tag) || mb_strlen($tag) < $minSearchTermLength) {
                continue;
            }

            $filtered[] = $tag;
        }

        return $filtered;
    }

    private function getMinSearchLength(string $languageId): ?int
    {
        if (isset($this->config[$languageId])) {
            return $this->config[$languageId];
        }

        $config = $this->connection->fetchAssociative('
            SELECT `min_search_length`
            FROM product_search_config
            WHERE language_id = :languageId
            LIMIT 1
        ', ['languageId' => Uuid::fromHexToBytes($languageId)]);

        if (empty($config)) {
            return null;
        }

        return $this->config[$languageId] = (int) ($config['min_search_length'] ?? self::DEFAULT_MIN_SEARCH_TERM_LENGTH);
    }
}
