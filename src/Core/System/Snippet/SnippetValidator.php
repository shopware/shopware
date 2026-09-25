<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;
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
#[BecomesInternal(version: 'v6.8.0')]
readonly class SnippetValidator implements SnippetValidatorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private SnippetFileCollection $loadedSnippetFiles,
        private SnippetFileHandler $snippetFileHandler,
        private string $projectDir
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
        return $this->validateFiles($this->getAllFiles(), $this->projectDir);
    }

    /**
     * Validates the snippet files below `$directory` (an extension root, for example) instead of the core bundles;
     * the allow list is `$directory/snippet-validation.json`.
     */
    public function getValidationFor(string $directory): SnippetValidationStruct
    {
        $files = new SnippetFileCollection();
        $this->hydrateFiles($this->snippetFileHandler->findAdministrationSnippetFilesBelow($directory), $files);
        $this->hydrateFiles($this->snippetFileHandler->findStorefrontSnippetFilesBelow($directory), $files);

        return $this->validateFiles($files, $directory);
    }

    protected function getAllFiles(): SnippetFileCollection
    {
        $snippetFiles = $this->loadedSnippetFiles->filter(static function (AbstractSnippetFile $snippetFile) {
            return $snippetFile instanceof GenericSnippetFile;
        });

        $this->hydrateFiles($this->snippetFileHandler->findAdministrationSnippetFiles(), $snippetFiles);
        $this->hydrateFiles($this->snippetFileHandler->findStorefrontSnippetFiles(), $snippetFiles);

        return $snippetFiles;
    }

    private function validateFiles(SnippetFileCollection $files, string $rootDir): SnippetValidationStruct
    {
        $invalidPluralization = new InvalidPluralizationCollection();
        $snippetFileMappings = [];
        foreach ($files as $snippetFile) {
            if (!\array_key_exists($snippetFile->getIso(), $snippetFileMappings)) {
                $snippetFileMappings[$snippetFile->getIso()] = [];
            }

            $json = $this->snippetFileHandler->openJsonFile($snippetFile->getPath());

            foreach ($this->getRecursiveArrayKeys($json) as $keyValue) {
                $key = key($keyValue);
                \assert(\is_string($key));

                $value = array_shift($keyValue);
                \assert(\is_string($value));

                $path = str_ireplace($rootDir, '', $snippetFile->getPath());

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

        /**
         * @deprecated tag:v6.8.0 - Validation of legacy snippet locales will be removed
         */
        $allowedEmptyKeys = $this->loadEmptyTranslationAllowList($rootDir);

        $legacyMissingSnippets = $this->findMissingSnippets($snippetFileMappings, ['en-GB', 'de-DE'], $allowedEmptyKeys);

        $missingSnippets = $this->findMissingSnippets($snippetFileMappings, ['en', 'de'], $allowedEmptyKeys);

        return new SnippetValidationStruct(
            new MissingSnippetCollection(array_merge($missingSnippets->getElements(), $legacyMissingSnippets->getElements())),
            $invalidPluralization,
        );
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
    private function hydrateFiles(array $files, SnippetFileCollection $collection): SnippetFileCollection
    {
        foreach ($files as $filePath) {
            $fileName = basename($filePath);

            $collection->add(new GenericSnippetFile(
                $fileName,
                $filePath,
                $this->getLocaleFromFileName($fileName),
                'Shopware',
                false,
                '',
            ));
        }

        return $collection;
    }

    private function getLocaleFromFileName(string $fileName): string
    {
        if (preg_match(SnippetPatterns::CORE_SNIPPET_FILE_PATTERN, $fileName, $matches)) {
            return $matches['locale'];
        }

        if (preg_match(SnippetPatterns::ADMIN_SNIPPET_FILE_PATTERN, $fileName, $matches)) {
            return $matches['locale'];
        }

        return 'en';
    }

    /**
     * @param array<string, mixed> $dataSet
     *
     * @return list<array<string, mixed>>
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
     * The `emptyTranslations` section of the root directory's `snippet-validation.json`, split into `administration`
     * and `storefront` like the ESLint counterpart: snippet keys (or whole namespaces, `foo` covers `foo.bar`)
     * that may stay empty in one locale while another locale carries a translation, each mapped to the reason.
     * SNIPPET_ALLOW_LIST_DISABLED bypasses it.
     *
     * @return list<string>
     */
    private function loadEmptyTranslationAllowList(string $rootDir): array
    {
        if (EnvironmentHelper::getVariable('SNIPPET_ALLOW_LIST_DISABLED')) {
            return [];
        }

        $path = $rootDir . '/' . SnippetFileHandler::VALIDATION_CONFIG;
        if (!$this->snippetFileHandler->exists($path)) {
            return [];
        }

        $section = $this->snippetFileHandler->openJsonFile($path)['emptyTranslations'] ?? [];
        if (!\is_array($section)) {
            return [];
        }

        $allowedKeys = [];
        foreach ($section as $domain) {
            if (\is_array($domain)) {
                $allowedKeys = [...$allowedKeys, ...array_keys($domain)];
            }
        }

        return $allowedKeys;
    }

    /**
     * @param list<string> $allowedEmptyKeys
     */
    private function isEmptyTranslationAllowed(array $allowedEmptyKeys, string $snippetKeyPath): bool
    {
        foreach ($allowedEmptyKeys as $allowedKey) {
            if ($snippetKeyPath === $allowedKey || str_starts_with($snippetKeyPath, $allowedKey . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $snippetFileMappings
     * @param list<string> $availableISOs
     * @param list<string> $allowedEmptyKeys
     */
    private function findMissingSnippets(array $snippetFileMappings, array $availableISOs, array $allowedEmptyKeys): MissingSnippetCollection
    {
        $missingSnippetsArray = [];
        foreach ($availableISOs as $isoKey => $availableISO) {
            $tempISOs = $availableISOs;

            if (!isset($snippetFileMappings[$availableISO])) {
                continue;
            }

            foreach ($snippetFileMappings[$availableISO] as $snippetKeyPath => $snippetFileMeta) {
                unset($tempISOs[$isoKey]);

                foreach ($tempISOs as $tempISO) {
                    if (!isset($snippetFileMappings[$tempISO])) {
                        continue;
                    }

                    // An empty value next to a translated one is a placeholder, not a translation
                    $isTranslated = \array_key_exists($snippetKeyPath, $snippetFileMappings[$tempISO])
                        && (
                            $snippetFileMappings[$tempISO][$snippetKeyPath]['availableValue'] !== ''
                            || $snippetFileMeta['availableValue'] === ''
                            || $this->isEmptyTranslationAllowed($allowedEmptyKeys, $snippetKeyPath)
                        );

                    if ($isTranslated) {
                        continue;
                    }

                    $missingSnippetsArray[$tempISO][$snippetKeyPath] = [
                        'path' => $snippetFileMeta['path'],
                        'availableISO' => $availableISO,
                        'availableValue' => $snippetFileMeta['availableValue'],
                        'keyPath' => $snippetKeyPath,
                    ];
                }
            }
        }

        return $this->hydrateMissingSnippets($missingSnippetsArray);
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
