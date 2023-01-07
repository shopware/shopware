<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Mock;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

/**
 * @internal
 */
class MockSnippetFile extends AbstractSnippetFile
{
    private string $name;

    private bool $isBase;

    private string $iso;

    private string $technicalName;

    private string $content;

    public function __construct(string $name, ?string $iso = null, string $content = '{}', bool $isBase = true, string $technicalName = 'mock')
    {
        $this->name = $name;
        $this->iso = $iso ?? $name;
        $this->isBase = $isBase;
        $this->technicalName = $technicalName;
        $this->content = $content;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return sprintf('%s/_fixtures/%s.json', __DIR__, $this->getName());
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
