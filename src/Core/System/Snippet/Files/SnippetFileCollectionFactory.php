<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class SnippetFileCollectionFactory
{
    /**
     * @internal
     */
    public function __construct(private readonly SnippetFileLoaderInterface $snippetFileLoader)
    {
    }

    public function createSnippetFileCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection();
        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        return $collection;
    }
}
