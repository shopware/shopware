<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\SnippetPatterns;

/**
 * @extends Collection<AbstractSnippetFile>
 */
#[Package('discovery')]
class SnippetFileCollection extends Collection
{
    /**
     * @var array<string, string>
     */
    private const CANONICAL_LANGUAGE_MAP = ['en' => 'en-GB'];

    /**
     * @var array<string, bool>|null
     */
    private ?array $mapping = null;

    /**
     * @param AbstractSnippetFile $snippetFile
     */
    public function add($snippetFile): void
    {
        $this->mapping = null;
        $this->set(null, $snippetFile);
    }

    public function get($key): ?AbstractSnippetFile
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return $this->getByName($key);
    }

    public function set($key, $element): void
    {
        $this->mapping = null;
        parent::set($key, $element);
    }

    public function clear(): void
    {
        $this->mapping = null;
        parent::clear();
    }

    public function remove($key): void
    {
        $this->mapping = null;
        parent::remove($key);
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
        return array_filter($this->toArray(), static fn ($file) => $file['isBase'] === $isBase);
    }

    /**
     * @return list<array{author: string, iso: string, isBase: bool, name: string, path: string}>
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
     * @return list<AbstractSnippetFile>
     */
    public function getSnippetFilesByIso(string $iso): array
    {
        $list = $this->getListSortedByIso();

        return $list[$iso] ?? [];
    }

    /**
     * Returns snippet files for the given locale with a canonical-locale fallback.
     *
     * Bare-language files (e.g. "de") are intentionally excluded here; they are loaded separately via the
     * {@see SnippetService::getStorefrontSnippets()} fallback-locale mechanism.
     *
     * For locales without a region part (e.g. "de"), the result is identical to {@see getSnippetFilesByIso()}.
     *
     * @return list<AbstractSnippetFile>
     */
    public function getSnippetFilesWithLocaleFallback(string $locale): array
    {
        if (!preg_match(SnippetPatterns::COMPLETE_LOCALE_PATTERN, $locale, $matches) || ($matches['region'] ?? '') === '') {
            return $this->getSnippetFilesByIso($locale);
        }

        $agnosticLanguage = $matches['language'];
        $canonicalIso = self::CANONICAL_LANGUAGE_MAP[$agnosticLanguage] ?? ($agnosticLanguage . '-' . strtoupper($agnosticLanguage));

        $list = $this->getListSortedByIso();

        $result = [];

        if ($canonicalIso !== $locale) {
            foreach ($list[$canonicalIso] ?? [] as $file) {
                $result[] = $file;
            }
        }

        foreach ($list[$locale] ?? [] as $file) {
            $result[] = $file;
        }

        return $result;
    }

    public function getBaseFileByIso(string $iso): AbstractSnippetFile
    {
        foreach ($this->getSnippetFilesByIso($iso) as $file) {
            if (!$file->isBase()) {
                continue;
            }

            return $file;
        }

        throw SnippetException::snippetFileNotRegistered($iso);
    }

    public function getApiAlias(): string
    {
        return 'snippet_file_collection';
    }

    public function hasFileForPath(string $filePath): bool
    {
        if ($this->mapping === null) {
            $this->mapping = [];
            foreach ($this->elements as $element) {
                $realPath = realpath($element->getPath());
                if ($realPath !== false) {
                    $this->mapping[$realPath] = true;
                }
            }
        }

        $realFilePath = realpath($filePath);
        if ($realFilePath === false) {
            return false;
        }

        return isset($this->mapping[$realFilePath]);
    }

    protected function getExpectedClass(): ?string
    {
        return AbstractSnippetFile::class;
    }

    /**
     * @return array<string, list<AbstractSnippetFile>>
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
