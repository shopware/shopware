<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Mock;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

/**
 * @internal
 */
class MockSnippetFile extends AbstractSnippetFile
{
    private readonly string $iso;

    public function __construct(
        private readonly string $name,
        ?string $iso = null,
        private readonly string $content = '{}',
        private readonly bool $isBase = true,
        private readonly string $technicalName = 'mock'
    ) {
        $this->iso = $iso ?? $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return \sprintf('%s/_fixtures/%s.json', __DIR__, $this->getName());
    }

    public function getIso(): string
    {
        return $this->iso;
    }

    public function getAuthor(): string
    {
        return $this->name;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
