<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Snippet\Struct\SnippetBasicStruct;

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

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SnippetBasicStruct $snippet) {
            return $snippet->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SnippetBasicStruct $snippet) use ($id) {
            return $snippet->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SnippetBasicStruct::class;
    }
}
