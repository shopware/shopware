<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;

/**
 * A storefront snippet file that lives in the private filesystem, see {@see FilesystemStorefrontSnippets}.
 * `getPath()` is the path inside that filesystem, not a local one.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class FilesystemSnippetFile extends AbstractSnippetFile
{
    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly string $iso,
        private readonly string $author,
        private readonly bool $isBase,
        private readonly string $technicalName,
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
}
