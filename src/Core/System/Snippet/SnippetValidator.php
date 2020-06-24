<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetValidator implements SnippetValidatorInterface
{
    /**
     * @var SnippetFileInterface[]
     */
    private $snippetFiles;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(iterable $snippetFiles, string $projectDir)
    {
        $this->snippetFiles = $snippetFiles;
        $this->projectDir = $projectDir;
    }

    public function validate(): array
    {
        $snippetFileMappings = [];
        $availableIsos = [];
        foreach ($this->snippetFiles as $snippetFile) {
            $availableIsos[] = $snippetFile->getIso();

            if (!array_key_exists($snippetFile->getIso(), $snippetFileMappings)) {
                $snippetFileMappings[$snippetFile->getIso()] = [];
            }

            $json = json_decode(file_get_contents($snippetFile->getPath()), true);

            $jsonError = json_last_error();
            if ($jsonError !== 0) {
                throw new \RuntimeException(sprintf('Invalid JSON in snippet file at path \'%s\' with code \'%d\'', $snippetFile->getPath(), $jsonError));
            }

            foreach ($this->getRecursiveArrayKeys($json) as $keyPath) {
                $snippetFileMappings[$snippetFile->getIso()][$keyPath] = str_ireplace($this->projectDir, '', $snippetFile->getPath());
            }
        }

        return $this->findMissingSnippets($snippetFileMappings, $availableIsos);
    }

    private function getRecursiveArrayKeys(array $dataSet, string $keyString = ''): array
    {
        $keyPaths = [];

        foreach ($dataSet as $key => $data) {
            $key = $keyString . $key;

            if (!is_array($data)) {
                $keyPaths[] = $key;

                continue;
            }

            $keyPaths = array_merge($keyPaths, $this->getRecursiveArrayKeys($data, $key . '.'));
        }

        return $keyPaths;
    }

    private function findMissingSnippets(array $snippetFileMappings, array $availableIsos): array
    {
        $missingSnippetsArray = [];
        foreach ($availableIsos as $isoKey => $availableIso) {
            $tempIsos = $availableIsos;

            foreach ($snippetFileMappings[$availableIso] as $snippetKeyPath => $snippetFileMapping) {
                unset($tempIsos[$isoKey]);

                foreach ($tempIsos as $tempIso) {
                    if (!array_key_exists($snippetKeyPath, $snippetFileMappings[$tempIso])) {
                        $missingSnippetsArray[$tempIso][$snippetKeyPath] = $snippetFileMapping;
                    }
                }
            }
        }

        return $missingSnippetsArray;
    }
}
