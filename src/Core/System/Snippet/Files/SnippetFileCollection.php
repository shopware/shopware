<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Snippet\Exception\InvalidSnippetFileException;

/**
 * @extends Collection<AbstractSnippetFile>
 */
#[Package('system-settings')]
class SnippetFileCollection extends Collection
{
    /**
     * @param AbstractSnippetFile $snippetFile
     */
    public function add($snippetFile): void
    {
        $this->set(null, $snippetFile);
    }

    public function get($key): ?AbstractSnippetFile
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return $this->getByName($key);
    }

    public function getByName(string $key): ?AbstractSnippetFile
    {
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
        return array_filter($this->toArray(), fn ($file) => $file['isBase'] === $isBase);
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
     * @return array<int, AbstractSnippetFile>
     */
    public function getSnippetFilesByIso(string $iso): array
    {
        $list = $this->getListSortedByIso();

        return $list[$iso] ?? [];
    }

    /**
     * @throws InvalidSnippetFileException
     */
    public function getBaseFileByIso(string $iso): AbstractSnippetFile
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

        $filesWithMatchingPath = $this->filter(
            static fn (AbstractSnippetFile $file): bool => realpath($file->getPath()) === $filePath
        );

        return $filesWithMatchingPath->count() > 0;
    }

    protected function getExpectedClass(): ?string
    {
        return AbstractSnippetFile::class;
    }

    /**
     * @return array<string, array<int, AbstractSnippetFile>>
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
