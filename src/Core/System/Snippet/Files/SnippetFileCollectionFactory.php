<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;
/**
 * @package system-settings
 */
#[Package('system-settings')]
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
