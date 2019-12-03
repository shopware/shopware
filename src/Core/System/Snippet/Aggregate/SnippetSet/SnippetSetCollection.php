<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Aggregate\SnippetSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(SnippetSetEntity $entity)
 * @method void                  set(string $key, SnippetSetEntity $entity)
 * @method SnippetSetEntity[]    getIterator()
 * @method SnippetSetEntity[]    getElements()
 * @method SnippetSetEntity|null get(string $key)
 * @method SnippetSetEntity|null first()
 * @method SnippetSetEntity|null last()
 */
class SnippetSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SnippetSetEntity::class;
    }
}
