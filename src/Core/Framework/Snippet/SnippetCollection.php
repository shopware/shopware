<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SnippetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SnippetEntity::class;
    }
}
