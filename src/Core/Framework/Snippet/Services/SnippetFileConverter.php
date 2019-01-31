<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFileConverter implements SnippetFileConverterInterface
{
    /**
     * @var SnippetFlattenerInterface
     */
    private $snippetFlattener;

    /**
     * @var SnippetFileCollection
     */
    private $snippetFileCollection;

    public function __construct(
        SnippetFileCollection $snippetFileCollection,
        SnippetFlattenerInterface $snippetFlattener
    ) {
        $this->snippetFlattener = $snippetFlattener;
        $this->snippetFileCollection = $snippetFileCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function convert(SnippetSetEntity $snippetSet): array
    {
        $snippetFiles = $this->snippetFileCollection->getSnippetFilesByIso($snippetSet->getIso());

        $resultSet = [];
        /** @var SnippetFileInterface $snippetFile */
        foreach ($snippetFiles as $snippetFile) {
            $resultSet = array_replace_recursive(
                $resultSet,
                $this->getFileContent($snippetFile)
            );
        }

        return $this->snippetFlattener->flatten($resultSet);
    }

    private function getFileContent(SnippetFileInterface $snippetFile): array
    {
        $content = file_get_contents($snippetFile->getPath());

        return json_decode($content, true) ?: [];
    }
}
