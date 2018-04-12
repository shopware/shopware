<?php declare(strict_types=1);

namespace Shopware\Api\Context\Collection;

use Shopware\Api\Context\Struct\ContextCartModifierTranslationDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class ContextCartModifierTranslationDetailCollection extends ContextCartModifierTranslationBasicCollection
{
    /**
     * @var ContextCartModifierTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getContextCartModifiers(): ContextCartModifierBasicCollection
    {
        return new ContextCartModifierBasicCollection(
            $this->fmap(function (ContextCartModifierTranslationDetailStruct $contextCartModifierTranslation) {
                return $contextCartModifierTranslation->getContextCartModifier();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ContextCartModifierTranslationDetailStruct $contextCartModifierTranslation) {
                return $contextCartModifierTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ContextCartModifierTranslationDetailStruct::class;
    }
}
