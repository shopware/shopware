<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\MissingSnippetCollection;

/**
 * @phpstan-type Snippets array<string, string|Snippets>
 */
#[Package('discovery')]
class SnippetFixer
{
    /**
     * @internal
     */
    public function __construct(private readonly SnippetFileHandler $snippetFileHandler)
    {
    }

    /**
     * @param Snippets $invalidPluralization
     */
    public function fix(MissingSnippetCollection $missingSnippetCollection, array $invalidPluralization): void
    {
        $this->fixMissingSnippets($missingSnippetCollection);
        $this->fixInvalidPluralization($invalidPluralization);
    }

    private function fixMissingSnippets(MissingSnippetCollection $missingSnippetCollection): void
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

    /**
     * @param Snippets $json
     *
     * @return Snippets
     */
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

    /**
     * @param Snippets $invalidPluralization
     */
    private function fixInvalidPluralization(array $invalidPluralization): void
    {
        foreach ($invalidPluralization as $invalidSnippet) {
            $json = $this->snippetFileHandler->openJsonFile($invalidSnippet['path']);

            $json = $this->replaceInvalidPluralization(
                $json,
                $invalidSnippet['snippetKey'],
            );

            $this->snippetFileHandler->writeJsonFile($invalidSnippet['path'], $json);
        }
    }

    /**
     * @param Snippets $json
     *
     * @return Snippets
     */
    private function replaceInvalidPluralization(array $json, string $key): array
    {
        $keyParts = explode('.', $key);

        $currentJson = &$json;
        $lastKey = end($keyParts);
        reset($keyParts);
        foreach ($keyParts as $keyPart) {
            if ($keyPart === $lastKey) {
                $currentJson[$keyPart] = preg_replace('/\]\s*1\s*,\s*Inf\s*\[/i', '[0,Inf[', $currentJson[$keyPart]);

                continue;
            }

            $currentJson = &$currentJson[$keyPart];
        }

        return $json;
    }
}
