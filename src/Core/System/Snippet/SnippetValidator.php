<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Struct\InvalidPluralizationCollection;
use Shopware\Core\System\Snippet\Struct\InvalidPluralizationStruct;
use Shopware\Core\System\Snippet\Struct\MissingSnippetCollection;
use Shopware\Core\System\Snippet\Struct\MissingSnippetStruct;
use Shopware\Core\System\Snippet\Struct\SnippetValidationStruct;

/**
 * @phpstan-type MissingSnippetsArray array<string, array<string, array{
 *      path: string,
 *      availableISO: string,
 *      availableValue: string,
 *      keyPath: string
 * }>>
 */
#[Package('discovery')]
class SnippetValidator implements SnippetValidatorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SnippetFileCollection $deprecatedSnippetFiles,
        private readonly SnippetFileHandler $snippetFileHandler,
        private readonly string $projectDir
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use `getValidation()` instead
     *
     * @return MissingSnippetsArray
     */
    public function validate(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'The method  Will be removed, use `getValidation()` instead.'
        );

        $missingSnippetsArray = [];
        foreach ($this->getValidation()->missingSnippets as $entry) {
            $key = $entry->getKeyPath();
            $missingSnippetsArray[$entry->getMissingForISO()][$key] = [
                'path' => $entry->getFilePath(),
                'availableISO' => $entry->getAvailableISO(),
                'availableValue' => $entry->getAvailableTranslation(),
                'keyPath' => $key,
            ];
        }

        return $missingSnippetsArray;
    }

    public function getValidation(): SnippetValidationStruct
    {
        $files = $this->getAllFiles();

        $invalidPluralization = new InvalidPluralizationCollection();
        $snippetFileMappings = [];
        $availableISOs = [];
        foreach ($files as $snippetFile) {
            $availableISOs[] = $snippetFile->getIso();

            if (!\array_key_exists($snippetFile->getIso(), $snippetFileMappings)) {
                $snippetFileMappings[$snippetFile->getIso()] = [];
            }

            $json = $this->snippetFileHandler->openJsonFile($snippetFile->getPath());

            foreach ($this->getRecursiveArrayKeys($json) as $keyValue) {
                $key = key($keyValue);
                \assert(\is_string($key));

                $value = array_shift($keyValue);
                \assert(\is_string($value));

                $path = str_ireplace($this->projectDir, '', $snippetFile->getPath());

                $snippetFileMappings[$snippetFile->getIso()][$key] = [
                    'path' => $path,
                    'availableValue' => $value,
                ];

                $validationData = $this->hasInvalidPluralization($value, $path);

                if ($validationData['isInvalid']) {
                    $invalidPluralization->set($key, new InvalidPluralizationStruct(
                        $key,
                        $value,
                        $validationData['isFixable'],
                        $path,
                    ));
                }
            }
        }

        return new SnippetValidationStruct(
            $this->findMissingSnippets($snippetFileMappings, $availableISOs),
            $invalidPluralization,
        );
    }

    protected function getAllFiles(): SnippetFileCollection
    {
        $deprecatedFiles = $this->findDeprecatedSnippetFiles();
        $administrationFiles = $this->snippetFileHandler->findAdministrationSnippetFiles();
        $storefrontSnippetFiles = $this->snippetFileHandler->findStorefrontSnippetFiles();

        return $this->hydrateFiles(array_merge($deprecatedFiles, $administrationFiles, $storefrontSnippetFiles));
    }

    /**
     * @param MissingSnippetsArray $missingSnippetsArray
     */
    private function hydrateMissingSnippets(array $missingSnippetsArray): MissingSnippetCollection
    {
        $missingSnippetsCollection = new MissingSnippetCollection();
        foreach ($missingSnippetsArray as $locale => $missingSnippets) {
            foreach ($missingSnippets as $key => $missingSnippet) {
                $missingSnippetsCollection->add(new MissingSnippetStruct($key, $missingSnippet['path'], $missingSnippet['availableISO'], $missingSnippet['availableValue'], $locale));
            }
        }

        return $missingSnippetsCollection;
    }

    /**
     * @param array<string> $files
     */
    private function hydrateFiles(array $files): SnippetFileCollection
    {
        $snippetFileCollection = new SnippetFileCollection();
        foreach ($files as $filePath) {
            $fileName = basename($filePath);

            $snippetFileCollection->add(new GenericSnippetFile(
                $fileName,
                $filePath,
                $this->getLocaleFromFileName($fileName),
                'Shopware',
                false,
                '',
            ));
        }

        return $snippetFileCollection;
    }

    private function getLocaleFromFileName(string $fileName): string
    {
        $return = preg_match('/([a-z]{2}-[A-Z]{2})(?:\.base)?\.json$/', $fileName, $matches);

        // Snippet file name not known, return 'en-GB' per default
        if (!$return) {
            return 'en-GB';
        }

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $dataSet
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRecursiveArrayKeys(array $dataSet, string $keyString = ''): array
    {
        $keyPaths = [];

        foreach ($dataSet as $key => $data) {
            $key = $keyString . $key;

            if (!\is_array($data)) {
                $keyPaths[] = [
                    $key => $data,
                ];

                continue;
            }

            $keyPaths = [...$keyPaths, ...$this->getRecursiveArrayKeys($data, $key . '.')];
        }

        return $keyPaths;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $snippetFileMappings
     * @param array<int, string> $availableISOs
     */
    private function findMissingSnippets(array $snippetFileMappings, array $availableISOs): MissingSnippetCollection
    {
        $availableISOs = array_keys(array_flip($availableISOs));

        $missingSnippetsArray = [];
        foreach ($availableISOs as $isoKey => $availableISO) {
            $tempISOs = $availableISOs;

            foreach ($snippetFileMappings[$availableISO] as $snippetKeyPath => $snippetFileMeta) {
                unset($tempISOs[$isoKey]);

                foreach ($tempISOs as $tempISO) {
                    if (!\array_key_exists($snippetKeyPath, $snippetFileMappings[$tempISO])) {
                        $missingSnippetsArray[$tempISO][$snippetKeyPath] = [
                            'path' => $snippetFileMeta['path'],
                            'availableISO' => $availableISO,
                            'availableValue' => $snippetFileMeta['availableValue'],
                            'keyPath' => $snippetKeyPath,
                        ];
                    }
                }
            }
        }

        return $this->hydrateMissingSnippets($missingSnippetsArray);
    }

    /**
     * @return list<string>
     */
    private function findDeprecatedSnippetFiles(): array
    {
        return array_column($this->deprecatedSnippetFiles->toArray(), 'path');
    }

    /**
     * @return array{isInvalid: bool, isFixable: bool}
     */
    private function hasInvalidPluralization(string $snippetContent, string $filePath): array
    {
        $unformattedSnippet = strtolower(preg_replace('/\s+/', '', $snippetContent) ?: '');

        $isSymfonyTranslationFile = preg_match('/storefront|messages/i', $filePath);
        $hasPluralization = str_contains($snippetContent, '|');

        if (!$isSymfonyTranslationFile || !$hasPluralization) {
            return [
                'isInvalid' => false,
                'isFixable' => false,
            ];
        }

        $hasInvalidPluralization = !preg_match('/^(\{0\}.+\|)?(\{1\}.+\|)(\[0,inf\[.+)/i', $unformattedSnippet);
        $hasInvalidPluralizationRange = str_contains($unformattedSnippet, ']1,inf[');

        return [
            'isInvalid' => $hasInvalidPluralization || $hasInvalidPluralizationRange,
            'isFixable' => $hasInvalidPluralizationRange,
        ];
    }
}
