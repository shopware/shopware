<?php declare(strict_types=1);

namespace Shopware\Api\Context\Struct;

use Shopware\Api\Context\Collection\ContextCartModifierTranslationBasicCollection;

class ContextCartModifierDetailStruct extends ContextCartModifierBasicStruct
{
    /**
     * @var ContextCartModifierTranslationBasicCollection
     */
    protected $translations;

    public function getTranslations(): ContextCartModifierTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ContextCartModifierTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
