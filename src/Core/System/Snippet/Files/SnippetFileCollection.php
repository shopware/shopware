<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Snippet\Exception\InvalidSnippetFileException;

/**
 * @extends Collection<AbstractSnippetFile|SnippetFileInterface>
 *
 * @package system-settings
 */
class SnippetFileCollection extends Collection
{
    /**
     * @param AbstractSnippetFile $snippetFile
     *
     * @deprecated tag:v6.5.0 The parameter $snippetFile will be native typed
     */
    public function add($snippetFile): void
    {
        if (!$snippetFile instanceof AbstractSnippetFile) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Parameter `$snippetFile` of method "add()" in class "SnippetFileCollection" will be natively typed to `AbstractSnippetFile` from v6.5.0.0.'
            );
        }

        $this->set(null, $snippetFile);
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - return type hinted will be changed to AbstractSnippetFile
     */
    public function get($key): ?SnippetFileInterface
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return $this->getByName($key);
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $key will be native typed
     * @deprecated tag:v6.5.0 - reason:return-type-change - return type hinted will be changed to AbstractSnippetFile
     *
     * @param string $key
     */
    public function getByName($key): ?SnippetFileInterface
    {
        if (!\is_string($key)) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Parameter `$key` of method "getByName()" in class "SnippetFileCollection" will be natively typed to `string` from v6.5.0.0.'
            );
        }

        foreach ($this->elements as $index => $element) {
            if ($element->getName() === $key) {
                return $this->elements[$index];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilesArray(bool $isBase = true): array
    {
        return array_filter($this->toArray(), function ($file) use ($isBase) {
            return $file['isBase'] === $isBase;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->getListSortedByIso() as $isoFiles) {
            foreach ($isoFiles as $snippetFile) {
                $data[] = [
                    'name' => $snippetFile->getName(),
                    'iso' => $snippetFile->getIso(),
                    'path' => $snippetFile->getPath(),
                    'author' => $snippetFile->getAuthor(),
                    'isBase' => $snippetFile->isBase(),
                ];
            }
        }

        return $data;
    }

    /**
     * @return array<string>
     */
    public function getIsoList(): array
    {
        return array_keys($this->getListSortedByIso());
    }

    /**
     * @return array<int, AbstractSnippetFile|SnippetFileInterface>
     */
    public function getSnippetFilesByIso(string $iso): array
    {
        $list = $this->getListSortedByIso();

        return $list[$iso] ?? [];
    }

    /**
     * @throws InvalidSnippetFileException
     *
     * @deprecated tag:v6.5.0 - reason:return-type-change - return type hinted will be changed to AbstractSnippetFile
     */
    public function getBaseFileByIso(string $iso): SnippetFileInterface
    {
        foreach ($this->getSnippetFilesByIso($iso) as $file) {
            if (!$file->isBase()) {
                continue;
            }

            return $file;
        }

        throw new InvalidSnippetFileException($iso);
    }

    public function getApiAlias(): string
    {
        return 'snippet_file_collection';
    }

    public function hasFileForPath(string $filePath): bool
    {
        $filePath = realpath($filePath);

        $filesWithMatchingPath = $this->filter(/**
         * @deprecated tag:v6.5.0 native type hinted will be changed to AbstractSnippetFile
         */ static function (SnippetFileInterface $file) use ($filePath): bool {
            return realpath($file->getPath()) === $filePath;
        });

        return $filesWithMatchingPath->count() > 0;
    }

    protected function getExpectedClass(): ?string
    {
        if (Feature::isActive('v6.5.0.0')) {
            return AbstractSnippetFile::class;
        }

        return SnippetFileInterface::class;
    }

    /**
     * @return array<string, array<int, SnippetFileInterface|AbstractSnippetFile>>
     */
    private function getListSortedByIso(): array
    {
        $list = [];

        /** @var AbstractSnippetFile $element */
        foreach ($this->getIterator() as $element) {
            $list[$element->getIso()][] = $element;
        }

        return $list;
    }
}
