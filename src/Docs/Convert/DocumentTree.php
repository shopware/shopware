<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class DocumentTree
{
    /**
     * @var Document
     */
    private $root;

    /**
     * @var Document[]
     */
    private $documents = [];

    public function add(Document $document): void
    {
        $this->documents[] = $document;
    }

    public function getAll(): array
    {
        return $this->documents;
    }

    /**
     * @return Document[]
     */
    public function getCategories(): array
    {
        return array_filter($this->documents, static function (Document $document): bool {
            return $document->isCategory();
        });
    }

    public function getArticles(): array
    {
        return array_filter($this->documents, static function (Document $document): bool {
            return !$document->isCategory();
        });
    }

    public function findByAbsolutePath(string $absolutePath): Document
    {
        foreach ($this->documents as $document) {
            if ($document->getFile()->getRealPath() === realpath($absolutePath)) {
                return $document;
            }
        }

        throw new \RuntimeException(sprintf('No file found named %s', $absolutePath));
    }

    public function setRoot(Document $root): void
    {
        $this->root = $root;
    }

    public function getRoot(): ?Document
    {
        return $this->root;
    }
}
