<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Aggregate\SnippetSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SnippetSetCollection extends EntityCollection
{
    /**
     * @var SnippetSetStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SnippetSetStruct
    {
        return parent::get($id);
    }

    public function current(): SnippetSetStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return SnippetSetStruct::class;
    }
}
