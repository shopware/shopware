<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Feature;

class ProductSearchKeywordAnalyzer implements ProductSearchKeywordAnalyzerInterface
{
    public const CUSTOM_FIELDS = 'customFields';

    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    /**
     * @var AbstractTokenFilter|null
     */
    private $tokenFilter;

    public function __construct(TokenizerInterface $tokenizer, ?AbstractTokenFilter $tokenFilter = null)
    {
        $this->tokenizer = $tokenizer;
        $this->tokenFilter = $tokenFilter;
    }

    public function analyze(ProductEntity $product, Context $context): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();

        $keywords->add(new AnalyzedKeyword($product->getProductNumber(), 1000));

        $name = $product->getTranslation('name');
        if ($name) {
            $tokens = $this->tokenizer->tokenize((string) $name);

            if (Feature::isActive('FEATURE_NEXT_10552') && $this->tokenFilter) {
                $tokens = $this->tokenFilter->filter($tokens, $context);
            }

            foreach ($tokens as $token) {
                $keywords->add(new AnalyzedKeyword((string) $token, 700));
            }
        }

        if ($product->getManufacturer() && $product->getManufacturer()->getTranslation('name') !== null) {
            $keywords->add(new AnalyzedKeyword((string) $product->getManufacturer()->getTranslation('name'), 500));
        }
        if ($product->getManufacturerNumber()) {
            $keywords->add(new AnalyzedKeyword($product->getManufacturerNumber(), 500));
        }
        if ($product->getEan()) {
            $keywords->add(new AnalyzedKeyword($product->getEan(), 500));
        }
        if (!empty($product->getCustomSearchKeywords())) {
            foreach ($product->getCustomSearchKeywords() as $keyword) {
                $keywords->add(new AnalyzedKeyword($keyword, 800));
            }
        }

        return $keywords;
    }

    public function analyzeBaseOnSearchConfig(ProductEntity $product, Context $context, ProductSearchConfigFieldCollection $configFields): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();
        foreach ($configFields as $configField) {
            if (!$configField->getSearchable()) {
                continue;
            }

            $field = $configField->getField();
            $isTokenize = $configField->getTokenize();
            $ranking = $configField->getRanking();

            if (strpos($field, '.') === false) {
                $keywords->addAll($this->buildKeyWords(
                    $isTokenize,
                    $this->getFieldValue($product, $field),
                    $ranking
                ));

                continue;
            }

            $field = explode('.', $field);

            $associationName = $field[0];
            $propertyName = $field[1];

            if ($product->get($associationName) === null) {
                continue;
            }

            $keywords = $this->buildKeywordsBaseOnFieldFromConfig(
                $associationName,
                $propertyName,
                $ranking,
                $isTokenize,
                $product,
                $keywords
            );
        }

        return $keywords;
    }

    private function buildKeywordsBaseOnFieldFromConfig(
        string $associationName,
        string $propertyName,
        int $ranking,
        bool $isTokenize,
        ProductEntity $product,
        AnalyzedKeywordCollection $keywords
    ): AnalyzedKeywordCollection {
        if ($associationName === self::CUSTOM_FIELDS) {
            /** @var array $associationData */
            $associationData = $product->get($associationName);

            $keywords->addAll($this->buildKeyWords(
                $isTokenize,
                $associationData[$propertyName],
                $ranking
            ));

            return $keywords;
        }

        $associationData = $product->get($associationName);

        if ($associationData instanceof EntityCollection) {
            foreach ($associationData as $data) {
                $keywords->addAll($this->buildKeyWords(
                    $isTokenize,
                    $this->getFieldValue($data, $propertyName),
                    $ranking
                ));
            }

            return $keywords;
        }

        $keywords->addAll($this->buildKeyWords(
            $isTokenize,
            $this->getFieldValue($associationData, $propertyName),
            $ranking
        ));

        return $keywords;
    }

    /**
     * @param string|array $fieldValues
     */
    private function buildKeyWords(bool $isTokenize, $fieldValues, int $ranking): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();

        if (!\is_array($fieldValues)) {
            $fieldValues = [$fieldValues];
        }

        foreach ($fieldValues as $fieldValue) {
            if ($isTokenize) {
                $tokens = $this->tokenizer->tokenize((string) $fieldValue);
                foreach ($tokens as $token) {
                    $keywords->add(new AnalyzedKeyword((string) $token, $ranking));
                }

                continue;
            }

            if ($fieldValue) {
                $keywords->add(new AnalyzedKeyword((string) $fieldValue, $ranking));
            }
        }

        return $keywords;
    }

    /**
     * @return array|mixed
     */
    private function getFieldValue(Entity $entity, string $field)
    {
        if (!\array_key_exists($field, $entity->getTranslated()) || empty($entity->getTranslated()[$field])) {
            return [$entity->get($field)];
        }

        return $entity->getTranslated()[$field];
    }
}
