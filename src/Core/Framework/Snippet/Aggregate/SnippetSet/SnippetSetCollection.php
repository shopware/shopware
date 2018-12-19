<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Aggregate\SnippetSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SnippetSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SnippetSetEntity::class;
    }
}
