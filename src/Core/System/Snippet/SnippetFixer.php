<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\MissingSnippetCollection;

#[Package('system-settings')]
class SnippetFixer
{
    /**
     * @internal
     */
    public function __construct(private readonly SnippetFileHandler $snippetFileHandler)
    {
    }

    public function fix(MissingSnippetCollection $missingSnippetCollection): void
    {
        foreach ($missingSnippetCollection->getIterator() as $missingSnippetStruct) {
            // Replace e.g. en-GB to de-DE and en_GB to de_DE
            $newPath = str_replace(
                [
                    $missingSnippetStruct->getAvailableISO(),
                    str_replace('-', '_', $missingSnippetStruct->getAvailableISO()),
                ],
                [
                    $missingSnippetStruct->getMissingForISO(),
                    str_replace('-', '_', $missingSnippetStruct->getMissingForISO()),
                ],
                $missingSnippetStruct->getFilePath()
            );

            $json = $this->snippetFileHandler->openJsonFile($newPath);
            $json = $this->addTranslationUsingSnippetKey(
                $json,
                $missingSnippetStruct->getTranslation(),
                $missingSnippetStruct->getKeyPath()
            );

            $this->snippetFileHandler->writeJsonFile($newPath, $json);
        }
    }

    private function addTranslationUsingSnippetKey(array $json, string $translation, string $key): array
    {
        $keyParts = explode('.', $key);

        $currentJson = &$json;
        $lastKey = end($keyParts);
        reset($keyParts);
        foreach ($keyParts as $keyPart) {
            if ($keyPart === $lastKey) {
                $currentJson[$keyPart] = $translation;

                continue;
            }

            $currentJson = &$currentJson[$keyPart];
        }

        return $json;
    }
}
