<?php declare(strict_types=1);

namespace Shopware\Application\Context\Collection;

use Shopware\Application\Context\Struct\ContextCartModifierTranslationDetailStruct;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
