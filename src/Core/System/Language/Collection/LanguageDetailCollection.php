<?php declare(strict_types=1);

namespace Shopware\System\Language\Collection;

use Shopware\System\Language\Struct\LanguageDetailStruct;

class LanguageDetailCollection extends LanguageBasicCollection
{
    /**
     * @var LanguageDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (LanguageDetailStruct $language) {
                return $language->getParent();
            })
        );
    }

    public function getChildrenIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getChildren()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getChildren(): LanguageBasicCollection
    {
        $collection = new LanguageBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return LanguageDetailStruct::class;
    }
}
