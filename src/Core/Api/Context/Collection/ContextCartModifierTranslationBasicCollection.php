<?php declare(strict_types=1);

namespace Shopware\Api\Context\Collection;

use Shopware\Api\Context\Struct\ContextCartModifierTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class ContextCartModifierTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ContextCartModifierTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ContextCartModifierTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ContextCartModifierTranslationBasicStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ContextCartModifierTranslationBasicStruct $contextCartModifierTranslation) {
            return $contextCartModifierTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ContextCartModifierTranslationBasicStruct $contextCartModifierTranslation) use ($id) {
            return $contextCartModifierTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ContextCartModifierTranslationBasicStruct::class;
    }
}
