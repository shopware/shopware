<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files;

use Shopware\Core\Framework\Exception\InvalidLanguageFileException;
use Shopware\Core\Framework\Struct\Collection;

class LanguageFileCollection extends Collection
{
    /**
     * @param LanguageFileInterface $languageFile
     */
    public function add($languageFile): void
    {
        $this->set($languageFile->getName(), $languageFile);
    }

    public function getIsoList(): array
    {
        return array_keys($this->getListSortedByIso());
    }

    public function getLanguageFilesByIso(string $iso): array
    {
        $list = $this->getListSortedByIso();

        return $list[$iso] ?? [];
    }

    public function getBaseFileByIso(string $iso): LanguageFileInterface
    {
        $files = $this->getLanguageFilesByIso($iso);

        /** @var LanguageFileInterface $file */
        foreach ($files as $file) {
            if (!$file->isBase()) {
                continue;
            }

            return $file;
        }

        throw new InvalidLanguageFileException($iso);
    }

    protected function getExpectedClass(): ?string
    {
        return LanguageFileInterface::class;
    }

    private function getListSortedByIso(): array
    {
        $list = [];

        /** @var LanguageFileInterface $element */
        foreach ($this->elements as $element) {
            $list[$element->getIso()][] = $element;
        }

        return $list;
    }
}
