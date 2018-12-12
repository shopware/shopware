<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SearchDocumentCollection extends EntityCollection
{
    /**
     * @var SearchDocumentEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? SearchDocumentEntity
    {
        return parent::get($id);
    }

    public function current(): SearchDocumentEntity
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SearchDocumentEntity $document) {
            return $document->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SearchDocumentEntity $document) use ($id) {
            return $document->getLanguageId() === $id;
        });
    }

    public function getEntityIds(): array
    {
        return $this->fmap(function (SearchDocumentEntity $document) {
            return $document->getEntityId();
        });
    }

    public function filterByEntityId(string $id): self
    {
        return $this->filter(function (SearchDocumentEntity $document) use ($id) {
            return $document->getEntityId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SearchDocumentEntity::class;
    }
}
