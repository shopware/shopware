<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Mock;

use Shopware\Core\System\Snippet\Files\SortableSnippetFileInterface;

class MockSortableSnippetFile implements SortableSnippetFileInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isBase;

    /**
     * @var string
     */
    private $iso;

    /**
     * @var int
     */
    private int $priority;

    public function __construct(string $name, ?string $iso = null, string $content = '{}', bool $isBase = true, int $priority = 0)
    {
        $this->name = $name;
        $this->iso = $iso ?? $name;
        $this->isBase = $isBase;
        $this->priority = $priority;
        file_put_contents($this->getPath(), $content);
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

    public function getPriority(): int
    {
        return $this->priority;
    }
}
