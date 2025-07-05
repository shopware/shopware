<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 *
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: float}
 * @phpstan-type FilterConfig array{excludedTerms: array<string>, minSearchLength: int}
 */
#[Package('framework')]
class SearchConfigLoader implements ResetInterface, EventSubscriberInterface
{
    private const NOT_SUPPORTED_FIELDS = [
        'manufacturer.customFields',
        'categories.customFields',
    ];

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

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'product_search_config.written' => 'reset',
            'product_search_config_field.written' => 'reset',
        ];
    }

    public function reset(): void
    {
        $this->config = [];
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            /** @var array<SearchConfig> $config */
            $config = $this->connection->fetchAllAssociative(
                'SELECT
product_search_config.and_logic,
product_search_config_field.field,
product_search_config_field.tokenize,
product_search_config_field.ranking

FROM product_search_config
INNER JOIN product_search_config_field ON(product_search_config_field.product_search_config_id = product_search_config.id)
WHERE product_search_config.language_id = :languageId AND product_search_config_field.searchable = 1 AND product_search_config_field.field NOT IN(:excludedFields)',
                [
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'excludedFields' => self::NOT_SUPPORTED_FIELDS,
                ],
                [
                    'excludedFields' => ArrayParameterType::STRING,
                ]
            );

            if (!empty($config)) {
                return $config;
            }
        }

        throw ElasticsearchProductException::configNotFound();
    }

    /**
     * @return FilterConfig|null
     */
    public function loadFilterConfig(string $languageId): ?array
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

        $excludedTerms = \is_string($config['excluded_terms']) ? array_flip(json_decode($config['excluded_terms'], true, 512, \JSON_THROW_ON_ERROR)) : [];

        return $this->config[$languageId] = [
            'excludedTerms' => $excludedTerms,
            'minSearchLength' => (int) ($config['min_search_length'] ?? AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH),
        ];
    }
}
