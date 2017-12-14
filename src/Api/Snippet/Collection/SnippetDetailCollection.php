<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Collection;

use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\Snippet\Struct\SnippetDetailStruct;

class SnippetDetailCollection extends SnippetBasicCollection
{
    /**
     * @var SnippetDetailStruct[]
     */
    protected $elements = [];

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (SnippetDetailStruct $snippet) {
                return $snippet->getShop();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SnippetDetailStruct::class;
    }
}
