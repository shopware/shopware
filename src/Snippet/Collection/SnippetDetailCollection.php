<?php declare(strict_types=1);

namespace Shopware\Snippet\Collection;

use Shopware\Shop\Collection\ShopBasicCollection;
use Shopware\Snippet\Struct\SnippetDetailStruct;

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
