<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetList;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_foo_foo implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'foo_foo';
    }

    public function getPath(): string
    {
        return __DIR__ . '/foo_foo.json';
    }

    public function getIso(): string
    {
        return 'foo_foo';
    }

    public function getAuthor(): string
    {
        return 'foo';
    }

    public function isBase(): bool
    {
        return true;
    }
}
