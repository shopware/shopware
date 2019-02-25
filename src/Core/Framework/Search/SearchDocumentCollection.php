<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(SearchDocumentEntity $entity)
 * @method void                      set(string $key, SearchDocumentEntity $entity)
 * @method SearchDocumentEntity[]    getIterator()
 * @method SearchDocumentEntity[]    getElements()
 * @method SearchDocumentEntity|null get(string $key)
 * @method SearchDocumentEntity|null first()
 * @method SearchDocumentEntity|null last()
 */
class SearchDocumentCollection extends EntityCollection
{
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
