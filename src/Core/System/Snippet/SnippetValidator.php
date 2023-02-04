<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;

#[Package('system-settings')]
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
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        $files = $this->getAllFiles();

        $snippetFileMappings = [];
        $availableISOs = [];
        foreach ($files as $snippetFile) {
            $availableISOs[] = $snippetFile->getIso();

            if (!\array_key_exists($snippetFile->getIso(), $snippetFileMappings)) {
                $snippetFileMappings[$snippetFile->getIso()] = [];
            }

            $json = $this->snippetFileHandler->openJsonFile($snippetFile->getPath());

            foreach ($this->getRecursiveArrayKeys($json) as $keyValue) {
                $snippetFileMappings[$snippetFile->getIso()][key($keyValue)] = [
                    'path' => str_ireplace($this->projectDir, '', $snippetFile->getPath()),
                    'availableValue' => array_shift($keyValue),
                ];
            }
        }

        return $this->findMissingSnippets($snippetFileMappings, $availableISOs);
    }

    protected function getAllFiles(): SnippetFileCollection
    {
        $deprecatedFiles = $this->findDeprecatedSnippetFiles();
        $administrationFiles = $this->snippetFileHandler->findAdministrationSnippetFiles();
        $storefrontSnippetFiles = $this->snippetFileHandler->findStorefrontSnippetFiles();

        return $this->hydrateFiles(array_merge($deprecatedFiles, $administrationFiles, $storefrontSnippetFiles));
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
                ''
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
     * @param array<mixed> $dataSet
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
     *
     * @return array<string, mixed>
     */
    private function findMissingSnippets(array $snippetFileMappings, array $availableISOs): array
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

        return $missingSnippetsArray;
    }

    /**
     * @return array<string>
     */
    private function findDeprecatedSnippetFiles(): array
    {
        return array_column($this->deprecatedSnippetFiles->toArray(), 'path');
    }
}
