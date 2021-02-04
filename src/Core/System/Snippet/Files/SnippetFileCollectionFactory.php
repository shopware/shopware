<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

class SnippetFileCollectionFactory
{
    /**
     * @var iterable|SnippetFileInterface[]
     */
    private $snippetFiles;

    /**
     * @var SnippetFileLoaderInterface
     */
    private $snippetFileLoader;

    public function __construct(iterable $snippetFiles, SnippetFileLoaderInterface $snippetFileLoader)
    {
        $this->snippetFiles = $snippetFiles;
        $this->snippetFileLoader = $snippetFileLoader;
    }

    public function createSnippetFileCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection($this->snippetFiles);
        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        return $collection;
    }
}
