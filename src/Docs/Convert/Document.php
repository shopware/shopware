<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

use Symfony\Component\Finder\SplFileInfo;

class Document
{
    private const METATAG_REGEX = '/^\[(.*?)\]:\s*<>\((.*?)\)\s*?$/m';

    private const IGNORE_TAGS = [
        'titleDe',
    ];

    /**
     * @var Document
     */
    private $parent = null;

    /**
     * @var Document[]
     */
    private $children = [];
    /**
     * @var bool
     */
    private $isCatgory;
    /**
     * @var SplFileInfo
     */
    private $file;
    /**
     * @var string
     */
    private $baseUrl;
    /**
     * @var int
     */
    private $categoryId;

    public function __construct(SplFileInfo $file, bool $isCatgory, string $baseUrl)
    {
        $this->file = $file;
        $this->isCatgory = $isCatgory;
        $this->baseUrl = $baseUrl;
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }

    public function getUrlPart(): string
    {
        if ($this->isCatgory) {
            $part = basename($this->getFile()->getRelativePath());
        } else {
            $part = $this->getFile()->getBasename('.md');
        }

        $parts = explode('-', $part);

        array_shift($parts);

        return implode('-', $parts);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function isCategory(): bool
    {
        return $this->isCatgory;
    }

    public function setParent(Document $document): void
    {
        $this->parent = $document;
    }

    public function getParent(): ?Document
    {
        return $this->parent;
    }

    public function addChild(Document $child): void
    {
        $this->children[] = $child;
    }

    public function getChild(string $childPath): Document
    {
        foreach ($this->children as $child) {
            echo ($child->getFile()->getRelativePathname()) . PHP_EOL;
        }
    }

    public function loadRawMetadata(): array
    {
        $fileContents = $this->file->getContents();
        $metadata = [];

        $matches = [];
        if (!preg_match_all(self::METATAG_REGEX, $fileContents, $matches, PREG_SET_ORDER)) {
            throw new \InvalidArgumentException(sprintf('Missing metadata in %s', $this->file));
        }

        foreach ($matches as $match) {
            $metadata[$match[1]] = $match[2];
        }

        $metadata = array_filter($metadata, function (string $key): bool {
            return !in_array($key, self::IGNORE_TAGS, true);
        }, ARRAY_FILTER_USE_KEY);

        if (!$metadata) {
            throw new \InvalidArgumentException(sprintf('Missing metadata in %s', $this->file));
        }

        return $metadata;
    }

    public function getMetadata(): DocumentMetadata
    {
        return new DocumentMetadata($this);
    }

    public function getHtml(): DocumentHtml
    {
        return new DocumentHtml($this);
    }

    public function cliOut()
    {
        $result = [
            'path' => $this->file->getRealPath(),
        ];

        if (!$this->children) {
            return $result;
        }

        $result['children'] = [];

        foreach ($this->children as $child) {
            $result['children'][] = $child->cliOut();
        }

        return $result;
    }

    public function getPriority(): int
    {
        $path = dirname($this->file->getRealPath());
        $self = $this->getFile()->getBasename();

        if ($this->isCategory()) {
            $path = dirname($path);
            $self = pathinfo(dirname($this->getFile()->getRealPath()), PATHINFO_BASENAME);
        }

        $files = [];
        foreach (scandir($path, SCANDIR_SORT_ASCENDING) as $file) {
            $files[] = $file;
        }

        $index = array_search($self, $files, true);

        return count($files) - $index;
    }

    public function createParentChain(): array
    {
        $chain = [];
        $current = $this;
        do {
            $chain[] = $current;
            $current = $current->getParent();
        } while ($current !== null);

        return array_reverse($chain);
    }

    public function setCategoryId(int $id)
    {
        $this->categoryId = $id;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }
}
