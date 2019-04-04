<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class MockSnippetFile implements SnippetFileInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isBase;

    public function __construct(string $name, string $content = '{}', bool $isBase = true)
    {
        $this->name = $name;
        $this->isBase = $isBase;
        file_put_contents($this->getPath(), $content);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return sprintf('%s/%s.json', __DIR__, $this->getName());
    }

    public function getIso(): string
    {
        return $this->name;
    }

    public function getAuthor(): string
    {
        return $this->name;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }
}
