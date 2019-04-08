<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class DocumentTree
{
    /**
     * @var Document[]
     */
    private $roots = [];
    /**
     * @var Document[]
     */
    private $documents = [];

    public function add(Document $document): void
    {
        $this->documents[] = $document;
    }

    public function addRoot(Document $document): void
    {
        $this->roots[] = $document;
    }

    public function getAll(): array
    {
        return $this->documents;
    }

    public function getCategories(): array
    {
        return array_filter($this->documents, function (Document $document): bool {
            return $document->isCategory();
        });
    }

    public function getArticles(): array
    {
        return array_filter($this->documents, function (Document $document): bool {
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
}
