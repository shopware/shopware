<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Collection;

use Shopware\System\Touchpoint\Collection\TouchpointBasicCollection;
use Shopware\System\Snippet\Struct\SnippetDetailStruct;

class SnippetDetailCollection extends SnippetBasicCollection
{
    /**
     * @var SnippetDetailStruct[]
     */
    protected $elements = [];

    public function getTouchpoints(): TouchpointBasicCollection
    {
        return new TouchpointBasicCollection(
            $this->fmap(function (SnippetDetailStruct $snippet) {
                return $snippet->getTouchpoint();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SnippetDetailStruct::class;
    }
}
