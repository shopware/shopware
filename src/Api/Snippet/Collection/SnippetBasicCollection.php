<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Snippet\Struct\SnippetBasicStruct;

class SnippetBasicCollection extends EntityCollection
{
    /**
     * @var SnippetBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? SnippetBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): SnippetBasicStruct
    {
        return parent::current();
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (SnippetBasicStruct $snippet) {
            return $snippet->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): SnippetBasicCollection
    {
        return $this->filter(function (SnippetBasicStruct $snippet) use ($uuid) {
            return $snippet->getShopUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return SnippetBasicStruct::class;
    }
}
