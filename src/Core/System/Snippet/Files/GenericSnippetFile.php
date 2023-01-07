<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

/**
 * @package system-settings
 */
class GenericSnippetFile extends AbstractSnippetFile
{
    private string $name;

    private string $path;

    private string $iso;

    private string $author;

    private bool $isBase;

    private string $technicalName;

    /**
     * @deprecated tag:v6.5.0 - parameter $technicalName will be required
     */
    public function __construct(string $name, string $path, string $iso, string $author, bool $isBase, ?string $technicalName = null)
    {
        $this->name = $name;
        $this->path = $path;
        $this->iso = $iso;
        $this->author = $author;
        $this->isBase = $isBase;
        $this->technicalName = $technicalName ?? '';
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

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }
}
