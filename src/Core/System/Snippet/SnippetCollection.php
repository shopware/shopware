<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(SnippetEntity $entity)
 * @method void               set(string $key, SnippetEntity $entity)
 * @method SnippetEntity[]    getIterator()
 * @method SnippetEntity[]    getElements()
 * @method SnippetEntity|null get(string $key)
 * @method SnippetEntity|null first()
 * @method SnippetEntity|null last()
 */
class SnippetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetEntity::class;
    }
}
