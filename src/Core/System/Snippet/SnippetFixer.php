<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Shopware\Core\System\Snippet\Struct\InvalidPluralizationCollection;
use Shopware\Core\System\Snippet\Struct\MissingSnippetCollection;

/**
 * @phpstan-import-type Snippets from ValidateSnippetsCommand
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
     * @deprecated tag:v6.8.0 reason:new-optional-parameter - Will get a second parameter `$invalidPluralization`
     */
    public function fix(MissingSnippetCollection $missingSnippetCollection /* , InvalidPluralizationCollection $invalidPluralization */): void
    {
        /** @var InvalidPluralizationCollection $invalidPluralization */
        $invalidPluralization = \func_num_args() === 2 ? func_get_arg(1) : new InvalidPluralizationCollection();

        if (!Feature::isActive('v6.8.0.0') && \func_num_args() < 2) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'New required parameter `$invalidPluralization` missing'
            );
        }

        $this->fixMissingSnippets($missingSnippetCollection);

        if ($invalidPluralization->count() < 1) {
            return;
        }

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

    private function fixInvalidPluralization(InvalidPluralizationCollection $invalidPluralization): void
    {
        foreach ($invalidPluralization->getIterator() as $invalidSnippet) {
            $json = $this->snippetFileHandler->openJsonFile($invalidSnippet->path);

            $json = $this->replaceInvalidPluralization(
                $json,
                $invalidSnippet->snippetKey,
            );

            $this->snippetFileHandler->writeJsonFile($invalidSnippet->path, $json);
        }
    }

    /**
     * @param Snippets $json
     *
     * @return Snippets
     */
    private function addTranslationUsingSnippetKey(array $json, ?string $translation, string $key): array
    {
        if ($translation === null) {
            return $json;
        }

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
