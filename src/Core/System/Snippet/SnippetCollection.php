<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\ORM\EntityCollection;


class SnippetCollection extends EntityCollection
{
    /**
     * @var SnippetStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SnippetStruct
    {
        return parent::get($id);
    }

    public function current(): SnippetStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SnippetStruct $snippet) {
            return $snippet->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SnippetStruct $snippet) use ($id) {
            return $snippet->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SnippetStruct::class;
    }
}
