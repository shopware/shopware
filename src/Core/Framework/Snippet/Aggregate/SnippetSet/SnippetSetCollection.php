<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Aggregate\SnippetSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SnippetSetCollection extends EntityCollection
{
    /**
     * @var SnippetSetEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? SnippetSetEntity
    {
        return parent::get($id);
    }

    public function current(): SnippetSetEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return SnippetSetEntity::class;
    }
}
