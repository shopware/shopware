<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Framework\ORM\EntityCollection;

class SearchDocumentCollection extends EntityCollection
{
    /**
     * @var SearchDocumentStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SearchDocumentStruct
    {
        return parent::get($id);
    }

    public function current(): SearchDocumentStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SearchDocumentStruct $document) {
            return $document->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SearchDocumentStruct $document) use ($id) {
            return $document->getLanguageId() === $id;
        });
    }

    public function getEntityIds(): array
    {
        return $this->fmap(function (SearchDocumentStruct $document) {
            return $document->getEntityId();
        });
    }

    public function filterByEntityId(string $id): self
    {
        return $this->filter(function (SearchDocumentStruct $document) use ($id) {
            return $document->getEntityId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SearchDocumentStruct::class;
    }
}
