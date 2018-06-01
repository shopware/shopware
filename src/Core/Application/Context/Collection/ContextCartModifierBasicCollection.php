<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Collection;

use Shopware\Core\Application\Context\Struct\ContextCartModifierBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class ContextCartModifierBasicCollection extends EntityCollection
{
    /**
     * @var ContextCartModifierBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ContextCartModifierBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ContextCartModifierBasicStruct
    {
        return parent::current();
    }

    public function getContextRuleIds(): array
    {
        return $this->fmap(function (ContextCartModifierBasicStruct $contextCartModifier) {
            return $contextCartModifier->getContextRuleId();
        });
    }

    public function filterByContextRuleId(string $id): self
    {
        return $this->filter(function (ContextCartModifierBasicStruct $contextCartModifier) use ($id) {
            return $contextCartModifier->getContextRuleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ContextCartModifierBasicStruct::class;
    }
}
