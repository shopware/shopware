<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

class GenericSnippetFile implements SnippetFileInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $iso;

    /**
     * @var string
     */
    private $author;

    /**
     * @var bool
     */
    private $isBase;

    public function __construct(string $name, string $path, string $iso, string $author, bool $isBase)
    {
        $this->name = $name;
        $this->path = $path;
        $this->iso = $iso;
        $this->author = $author;
        $this->isBase = $isBase;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }
}
