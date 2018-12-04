<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files;

use Shopware\Core\Framework\Exception\InvalidLanguageFileException;
use Shopware\Core\Framework\Struct\Collection;

class LanguageFileCollection extends Collection
{
    public function __construct(iterable $elements)
    {
        parent::__construct(iterator_to_array($elements, false));
    }

    public function add(LanguageFileInterface $languageFile): void
    {
        $this->elements[$languageFile->getName()] = $languageFile;
    }

    public function get(string $index): ?LanguageFileInterface
    {
        if ($this->has($index)) {
            return $this->elements[$index];
        }

        return null;
    }

    public function getIsoList(): array
    {
        return array_keys($this->getListSortedByIso());
    }

    public function getLanguageFilesByIso(string $iso): array
    {
        $list = $this->getListSortedByIso();

        return isset($list[$iso]) ? $list[$iso] : [];
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

    private function getListSortedByIso(): array
    {
        $list = [];
        foreach ($this->elements as $element) {
            $list[$element->getIso()][] = $element;
        }

        return $list;
    }
}
