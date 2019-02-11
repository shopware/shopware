<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetList;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_bar_bar implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'bar_bar';
    }

    public function getPath(): string
    {
        return __DIR__ . '/bar_bar.json';
    }

    public function getIso(): string
    {
        return 'bar_bar';
    }

    public function getAuthor(): string
    {
        return 'bar';
    }

    public function isBase(): bool
    {
        return true;
    }
}
