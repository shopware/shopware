<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\Framework\Snippet\Files\LanguageFileCollection;
use Shopware\Core\Framework\Snippet\Files\LanguageFileInterface;

class SnippetFileConverter implements SnippetFileConverterInterface
{
    /**
     * @var SnippetFlattenerInterface
     */
    private $snippetFlattener;

    /**
     * @var LanguageFileCollection
     */
    private $languageFileCollection;

    public function __construct(
        LanguageFileCollection $languageFileCollection,
        SnippetFlattenerInterface $snippetFlattener
    ) {
        $this->snippetFlattener = $snippetFlattener;
        $this->languageFileCollection = $languageFileCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function convert(SnippetSetEntity $snippetSet): array
    {
        $languageFiles = $this->languageFileCollection->getLanguageFilesByIso($snippetSet->getIso());

        $resultSet = [];
        /** @var LanguageFileInterface $languageFile */
        foreach ($languageFiles as $languageFile) {
            $resultSet = array_replace_recursive(
                $resultSet,
                $this->getFileContent($languageFile)
            );
        }

        return $this->snippetFlattener->flatten($resultSet);
    }

    private function getFileContent(LanguageFileInterface $languageFile): array
    {
        $content = file_get_contents($languageFile->getPath());

        return json_decode($content, true) ?: [];
    }
}
