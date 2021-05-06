<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;

class ProductSearchKeywordAnalyzer implements ProductSearchKeywordAnalyzerInterface
{
    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    /**
     * @var AbstractTokenFilter|null
     */
    private $tokenFilter;

    public function __construct(TokenizerInterface $tokenizer, AbstractTokenFilter $tokenFilter)
    {
        $this->tokenizer = $tokenizer;
        $this->tokenFilter = $tokenFilter;
    }

    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();

        foreach ($configFields as $configField) {
            $path = $configField['field'];
            $isTokenize = (bool) $configField['tokenize'];
            $ranking = (int) $configField['ranking'];

            $values = array_filter($this->resolveEntityValue($product, $path));

            if ($isTokenize) {
                $fieldValue = implode(' ', $values);
                $values = $this->tokenizer->tokenize($fieldValue);

                if ($this->tokenFilter) {
                    $values = $this->tokenFilter->filter($values, $context);
                }
            }

            foreach ($values as $value) {
                $keywords->add(new AnalyzedKeyword((string) $value, $ranking));
            }
        }

        return $keywords;
    }

    private function resolveEntityValue(Entity $entity, string $path): array
    {
        $value = $entity;
        $parts = explode('.', $path);

        // if property does not exist, try to omit the first key as it may contains the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        while (\count($parts) > 0) {
            $part = array_shift($parts);

            if ($value === null) {
                break;
            }

            try {
                if ($value instanceof EntityCollection) {
                    $values = [];
                    if (!empty($parts)) {
                        $part .= sprintf('.%s', implode('.', $parts));
                    }
                    foreach ($value as $item) {
                        $values = array_merge($values, $this->resolveEntityValue($item, $part));
                    }
                    $value = $values;
                } else {
                    if ($value->get($part) === null) {
                        // if we are at the destination entity and it does not have a value for the field
                        // on it's on, then try to get the translation fallback
                        $value = $value->getTranslation($part);
                    } else {
                        $value = $value->get($part);
                    }
                }

                if (\is_array($value)) {
                    return $value;
                }
            } catch (\InvalidArgumentException $ex) {
                if (!$smartDetect) {
                    throw $ex;
                }
            }

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;
        }

        return (array) $value;
    }
}
