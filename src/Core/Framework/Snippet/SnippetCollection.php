<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SnippetCollection extends EntityCollection
{
    public function getLanguageIds(): array
    {
        return $this->fmap(function (SnippetEntity $snippet) {
            return $snippet->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (SnippetEntity $snippet) use ($id) {
            return $snippet->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return SnippetEntity::class;
    }
}
