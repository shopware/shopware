<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Snippet\Struct\SnippetBasicStruct;

class SnippetBasicCollection extends EntityCollection
{
    /**
     * @var SnippetBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SnippetBasicStruct
    {
        return parent::get($id);
    }

    public function current(): SnippetBasicStruct
    {
        return parent::current();
    }

    public function getApplicationIds(): array
    {
        return $this->fmap(function (SnippetBasicStruct $snippet) {
            return $snippet->getApplicationId();
        });
    }

    public function filterByApplicationId(string $id): self
    {
        return $this->filter(function (SnippetBasicStruct $snippet) use ($id) {
            return $snippet->getApplicationId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SnippetBasicStruct::class;
    }
}
