<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Collection;

use Shopware\Api\Application\Collection\ApplicationBasicCollection;
use Shopware\Api\Snippet\Struct\SnippetDetailStruct;

class SnippetDetailCollection extends SnippetBasicCollection
{
    /**
     * @var SnippetDetailStruct[]
     */
    protected $elements = [];

    public function getApplications(): ApplicationBasicCollection
    {
        return new ApplicationBasicCollection(
            $this->fmap(function (SnippetDetailStruct $snippet) {
                return $snippet->getApplication();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SnippetDetailStruct::class;
    }
}
