<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

/**
 * @package system-settings
 */
class SnippetFileCollectionFactory
{
    private SnippetFileLoaderInterface $snippetFileLoader;

    /**
     * @internal
     */
    public function __construct(SnippetFileLoaderInterface $snippetFileLoader)
    {
        $this->snippetFileLoader = $snippetFileLoader;
    }

    public function createSnippetFileCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection();
        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        return $collection;
    }
}
