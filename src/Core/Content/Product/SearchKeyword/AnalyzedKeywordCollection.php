<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                 set(string $key, AnalyzedKeyword $entity)
 * @method AnalyzedKeyword[]    getIterator()
 * @method AnalyzedKeyword[]    getElements()
 * @method AnalyzedKeyword|null get(string $key)
 * @method AnalyzedKeyword|null first()
 * @method AnalyzedKeyword|null last()
 */
class AnalyzedKeywordCollection extends Collection
{
    public function add($element): void
    {
        $keyword = $element->getKeyword();

        if (!$this->has($keyword)) {
            $this->elements[$keyword] = $element;

            return;
        }

        $existing = $this->get($keyword);
        if ($existing->getRanking() > $element->getRanking()) {
            return;
        }

        $this->elements[$keyword] = $element;
    }

    public function getApiAlias(): string
    {
        return 'product_search_keyword_analyzed_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return AnalyzedKeyword::class;
    }
}
