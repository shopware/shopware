<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class GenericSnippetFile extends AbstractSnippetFile
{
    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly string $iso,
        private readonly string $author,
        private readonly bool $isBase,
        private string $technicalName
    ) {
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
