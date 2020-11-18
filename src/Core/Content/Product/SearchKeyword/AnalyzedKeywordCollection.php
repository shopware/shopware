<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method AnalyzedKeyword[]    getIterator()
 * @method AnalyzedKeyword[]    getElements()
 * @method AnalyzedKeyword|null get(string $key)
 * @method AnalyzedKeyword|null first()
 * @method AnalyzedKeyword|null last()
 */
class AnalyzedKeywordCollection extends Collection
{
    /**
     * @param AnalyzedKeyword $element
     */
    public function add($element): void
    {
        $this->validateType($element);

        $keyword = $element->getKeyword();
        $this->elements[$keyword] = $this->getBest($element, $keyword);
    }

    /**
     * @param string|int      $key
     * @param AnalyzedKeyword $element
     */
    public function set($key, $element): void
    {
        $this->validateType($element);

        $this->elements[$element->getKeyword()] = $element;
    }

    public function getApiAlias(): string
    {
        return 'product_search_keyword_analyzed_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return AnalyzedKeyword::class;
    }

    private function getBest(AnalyzedKeyword $new, string $keyword): AnalyzedKeyword
    {
        $existing = $this->has($keyword) ? $this->get($keyword) : null;
        if ($existing === null) {
            return $new;
        }

        return $new->getRanking() > $existing->getRanking() ? $new : $existing;
    }
}
