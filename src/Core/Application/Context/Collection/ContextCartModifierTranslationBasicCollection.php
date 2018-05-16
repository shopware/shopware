<?php declare(strict_types=1);

namespace Shopware\Application\Context\Collection;

use Shopware\Application\Context\Struct\ContextCartModifierTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

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
