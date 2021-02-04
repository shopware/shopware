<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Feature;

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

    public function __construct(TokenizerInterface $tokenizer, ?AbstractTokenFilter $tokenFilter = null)
    {
        $this->tokenizer = $tokenizer;
        $this->tokenFilter = $tokenFilter;
    }

    /**@feature-deprecated (flag:FEATURE_NEXT_10552) tag:v6.4.0 - Parameter $configFields will be mandatory in future implementation */
    public function analyze(ProductEntity $product, Context $context /*, ?array $configFields */): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();

        if (Feature::isActive('FEATURE_NEXT_10552') && \func_num_args() === 3) {
            $configFields = func_get_arg(2);

            foreach ($configFields as $configField) {
                $path = $configField['field'];
                $isTokenize = (bool) $configField['tokenize'];
                $ranking = (int) $configField['ranking'];

                $values = array_filter($this->resolveEntityValue($product, $path));

                if ($isTokenize) {
                    $fieldValue = implode(' ', $values);
                    $values = $this->tokenizer->tokenize((string) $fieldValue);

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

        $keywords->add(new AnalyzedKeyword($product->getProductNumber(), 1000));

        $name = $product->getTranslation('name');
        if ($name) {
            $tokens = $this->tokenizer->tokenize((string) $name);

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
                        $part = $part . '.' . implode('.', $parts);
                    }
                    foreach ($value as $item) {
                        $values = array_merge($values, $this->resolveEntityValue($item, $part));
                    }
                    $value = $values;
                } else {
                    $value = $value->get($part);
                }

                if (\is_array($value)) {
                    return $value;
                }

                // if we are at the destination entity and it does not have a value for the field
                // on it's on, then try to get the translation fallback
                if ($value === null) {
                    $value = $entity->getTranslation((string) $part);
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
