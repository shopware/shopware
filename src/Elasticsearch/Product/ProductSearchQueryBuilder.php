<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\FullText\MultiMatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\PrefixQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: int}
 */
#[Package('core')]
class ProductSearchQueryBuilder extends AbstractProductSearchQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinitionQueryHelper $helper,
        private readonly EntityDefinition $productDefinition,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly TokenizerInterface $tokenizer,
        private readonly CustomFieldService $customFieldService,
        private readonly SearchConfigLoader $configLoader
    ) {
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $bool = new BoolQuery();

        $searchConfig = $this->configLoader->load($context);

        $isAndSearch = $searchConfig[0]['and_logic'] === '1';

        $tokens = $this->tokenizer->tokenize((string) $criteria->getTerm());
        $tokens = $this->tokenFilter->filter($tokens, $context);
        $term = strtolower((string) $criteria->getTerm());
        if (!\in_array($term, $tokens, true)) {
            $tokens[] = $term;
        }

        foreach ($tokens as $originalToken) {
            $tokenBool = new BoolQuery();

            foreach ($searchConfig as $item) {
                $token = $originalToken;

                $config = new SearchFieldConfig((string) $item['field'], (int) $item['ranking'], (bool) $item['tokenize']);
                $field = $this->helper->getField($config->getField(), $this->productDefinition, $this->productDefinition->getEntityName(), false);
                $real = $field instanceof TranslatedField ? EntityDefinitionQueryHelper::getTranslatedField($this->productDefinition, $field) : $field;

                if ($config->isCustomField()) {
                    $real = $this->customFieldService->getCustomField(str_replace('customFields.', '', $config->getField()));

                    if ($real === null) {
                        continue;
                    }
                }

                if ($real instanceof IntField || $real instanceof FloatField) {
                    if (!\is_numeric($token)) {
                        continue;
                    }

                    $token = $real instanceof IntField ? (int) $token : (float) $token;
                }

                $association = $this->helper->getAssociationPath($config->getField(), $this->productDefinition);
                $root = $association ? explode('.', $association)[0] : null;
                $isTextField = $real instanceof StringField || $real instanceof LongTextField || $real instanceof ListField;

                if ($field instanceof TranslatedField) {
                    $this->buildTranslatedFieldTokenQueries($tokenBool, $token, $config, $context, $isTextField, $root);

                    continue;
                }

                if (!$isTextField) {
                    $this->buildNonTextTokenQuery($tokenBool, $token, $config, $root);

                    continue;
                }

                $this->buildTextTokenQuery($tokenBool, (string) $token, $config, $root);
            }

            $bool->add($tokenBool, $isAndSearch ? BoolQuery::MUST : BoolQuery::SHOULD);
        }

        return $bool;
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    private function buildTextTokenQuery(BoolQuery $tokenBool, string $token, SearchFieldConfig $config, ?string $root = null): void
    {
        $queries = [];

        $searchField = $config->getField() . '.search';

        $queries[] = new MatchQuery($searchField, $token, ['boost' => 5 * $config->getRanking(), 'fuzziness' => 0]);
        $queries[] = new MatchPhrasePrefixQuery($searchField, $token, ['boost' => $config->getRanking(), 'slop' => 5]);

        if ($config->tokenize()) {
            $ngramField = $config->isCustomField() ? $config->getField() : $config->getField() . '.ngram';
            $queries[] = new PrefixQuery($searchField, $token, ['boost' => $config->getRanking()]);
            $queries[] = new MatchQuery($searchField, $token, ['boost' => 3 * $config->getRanking(), 'fuzziness' => 'auto']);
            $queries[] = new MatchQuery($ngramField, $token, ['boost' => $config->getRanking()]);
        }

        foreach ($queries as $query) {
            if ($root) {
                $query = new NestedQuery($root, $query);
            }

            $tokenBool->add($query, BoolQuery::SHOULD);
        }
    }

    private function buildNonTextTokenQuery(BoolQuery $tokenBool, string|int|float $token, SearchFieldConfig $config, ?string $root = null): void
    {
        $query = new TermQuery($config->getField(), $token, ['boost' => 5 * $config->getRanking(), 'case_insensitive' => true]);

        if ($root) {
            $query = new NestedQuery($root, $query);
        }

        $tokenBool->add($query, BoolQuery::SHOULD);
    }

    private function buildTranslatedFieldTokenQueries(BoolQuery $tokenBool, string|int|float $token, SearchFieldConfig $config, Context $context, bool $isTextField, ?string $root = null): void
    {
        if (\count($context->getLanguageIdChain()) === 1) {
            $searchField = self::buildTranslatedFieldName($config, $context->getLanguageId());
            $config = new SearchFieldConfig($searchField, $config->getRanking(), $config->tokenize());

            if (!$isTextField) {
                $this->buildNonTextTokenQuery($tokenBool, $token, $config, $root);

                return;
            }

            $this->buildTextTokenQuery($tokenBool, (string) $token, $config, $root);

            return;
        }

        $multiMatchFields = [];
        $nonTextFields = [];
        $fuzzyMatchFields = [];
        $matchPhraseFields = [];
        $ngramFields = [];

        foreach ($context->getLanguageIdChain() as $languageId) {
            $nonTextFields[] = $this->buildTranslatedFieldName($config, $languageId);
            $searchField = $this->buildTranslatedFieldName($config, $languageId, 'search');

            $multiMatchFields[] = $searchField;
            $matchPhraseFields[] = $searchField;

            if ($config->tokenize()) {
                $ngramField = $this->buildTranslatedFieldName($config, $languageId, 'ngram');
                $fuzzyMatchFields[] = $searchField;
                $ngramFields[] = $ngramField;
            }
        }

        if ($isTextField) {
            $queries = [
                new MultiMatchQuery($multiMatchFields, $token, [
                    'type' => 'best_fields',
                    'lenient' => true,
                    'boost' => $config->getRanking() * 5,
                    'fuzziness' => 0,
                ]),
                new MultiMatchQuery($matchPhraseFields, $token, [
                    'type' => 'phrase_prefix',
                    'slop' => 5,
                    'boost' => $config->getRanking(),
                ]),
            ];

            if ($config->isCustomField()) {
                $queries[] = new MultiMatchQuery($nonTextFields, $token, [
                    'type' => 'best_fields',
                    'lenient' => true,
                    'boost' => $config->getRanking() * 3,
                    'fuzziness' => 'auto',
                ]);
            }
        } else {
            $dismax = new DisMaxQuery();

            foreach ($nonTextFields as $field) {
                $dismax->addQuery(new TermQuery($field, $token, ['boost' => $config->getRanking() * 5, 'case_insensitive' => true]));
            }

            $queries[] = $dismax;
        }

        if ($config->tokenize()) {
            $queries[] = new MultiMatchQuery($fuzzyMatchFields, $token, [
                'type' => 'best_fields',
                'boost' => $config->getRanking() * 3,
                'fuzziness' => 'auto',
            ]);

            $queries[] = new MultiMatchQuery($ngramFields, $token, [
                'type' => 'phrase',
                'boost' => $config->getRanking(),
            ]);
        }

        foreach ($queries as $query) {
            if ($root) {
                $query = new NestedQuery($root, $query);
            }

            $tokenBool->add($query, BoolQuery::SHOULD);
        }
    }

    private function buildTranslatedFieldName(SearchFieldConfig $fieldConfig, string $languageId, ?string $suffix = null): string
    {
        if ($fieldConfig->isCustomField()) {
            $parts = explode('.', $fieldConfig->getField());

            return sprintf('%s.%s.%s', $parts[0], $languageId, $parts[1]);
        }

        if ($suffix === null) {
            return sprintf('%s.%s', $fieldConfig->getField(), $languageId);
        }

        return sprintf('%s.%s.%s', $fieldConfig->getField(), $languageId, $suffix);
    }
}
