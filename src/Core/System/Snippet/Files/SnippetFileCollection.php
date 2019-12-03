<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Snippet\Exception\InvalidSnippetFileException;

/**
 * @method void                      set(?string $key, SnippetFileInterface $entity)
 * @method SnippetFileInterface[]    getIterator()
 * @method SnippetFileInterface[]    getElements()
 * @method SnippetFileInterface|null first()
 * @method SnippetFileInterface|null last()
 */
class SnippetFileCollection extends Collection
{
    /**
     * @param SnippetFileInterface $snippetFile
     */
    public function add($snippetFile): void
    {
        $this->set(null, $snippetFile);
    }

    public function get($key): ?SnippetFileInterface
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return $this->getByName($key);
    }

    public function getByName($key): ?SnippetFileInterface
    {
        foreach ($this->elements as $index => $element) {
            if ($element->getName() === $key) {
                return $this->elements[$index];
            }
        }

        return null;
    }

    public function getFilesArray(bool $isBase = true): array
    {
        return array_filter($this->toArray(), function ($file) use ($isBase) {
            return $file['isBase'] === $isBase;
        });
    }

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
     * @return string[]
     */
    public function getIsoList(): array
    {
        return array_keys($this->getListSortedByIso());
    }

    /**
     * @return SnippetFileInterface[]
     */
    public function getSnippetFilesByIso(string $iso): array
    {
        $list = $this->getListSortedByIso();

        return $list[$iso] ?? [];
    }

    /**
     * @throws InvalidSnippetFileException
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

    protected function getExpectedClass(): ?string
    {
        return SnippetFileInterface::class;
    }

    /**
     * @return array<string, SnippetFileInterface[]>
     */
    private function getListSortedByIso(): array
    {
        $list = [];

        foreach ($this->getIterator() as $element) {
            $list[$element->getIso()][] = $element;
        }

        return $list;
    }
}
