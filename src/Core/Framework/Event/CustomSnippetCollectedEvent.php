<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippetCollection;
use Symfony\Contracts\EventDispatcher\Event;

class CustomSnippetCollectedEvent extends Event
{
    private CustomSnippetCollection $snippetCollection;

    public function __construct(CustomSnippetCollection $snippetCollection)
    {
        $this->snippetCollection = $snippetCollection;
    }

    public function getSnippetCollection(): CustomSnippetCollection
    {
        return $this->snippetCollection;
    }
}
