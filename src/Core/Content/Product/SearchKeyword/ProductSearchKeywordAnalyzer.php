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
    private const MAXIMUM_KEYWORD_LENGTH = 500;

    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    /**
     * @var AbstractTokenFilter
     */
    private $tokenFilter;

    /**
     * @internal
     */
    public function __construct(TokenizerInterface $tokenizer, AbstractTokenFilter $tokenFilter)
    {
        $this->tokenizer = $tokenizer;
        $this->tokenFilter = $tokenFilter;
    }

    /**
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     */
    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection
    {
        $keywords = new AnalyzedKeywordCollection();

        foreach ($configFields as $configField) {
            $path = $configField['field'];
            $isTokenize = (bool) $configField['tokenize'];
            $ranking = (int) $configField['ranking'];

            $values = array_filter($this->resolveEntityValue($product, $path));

            if ($isTokenize) {
                try {
                    $values = $this->tokenize($values, $context);
                } catch (\Throwable $error) {
                    // Can occur if the resolved value is a nested array. This prevents the implode() from being executed. We ignore this error at this point to allow some error tolerance in the configuration
                    continue;
                }
            }

            foreach ($values as $value) {
                try {
                    // even the field is non tokenize, if it reached 500 chars, we should break it anyway
                    $parts = array_filter(mb_str_split((string) $value, self::MAXIMUM_KEYWORD_LENGTH));

                    foreach ($parts as $part) {
                        $keywords->add(new AnalyzedKeyword((string) $part, $ranking));
                    }
                } catch (\Throwable $error) {
                    // Can occur if the resolved value is a nested array. This prevents the string cast from being executed (Array to string conversion). We ignore this error at this point to allow some error tolerance in the configuration
                }
            }
        }

        return $keywords;
    }

    /**
     * @param array<int, string> $values
     *
     * @return array<int, string>
     */
    private function tokenize(array $values, Context $context): array
    {
        $values = $this->tokenizer->tokenize(
            implode(' ', $values)
        );

        return $this->tokenFilter->filter($values, $context);
    }

    /**
     * @return array<int, string>
     */
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

                    return $values;
                }

                if ($value instanceof Entity) {
                    if ($value->get($part) === null) {
                        // if we are at the destination entity and it does not have a value for the field
                        // on it's on, then try to get the translation fallback
                        $value = $value->getTranslation($part);
                    } else {
                        $value = $value->get($part);
                    }
                } elseif (\is_array($value)) {
                    $value = $value[$part] ?? null;
                }

                if (\is_array($value) && !empty($parts)) {
                    continue;
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
