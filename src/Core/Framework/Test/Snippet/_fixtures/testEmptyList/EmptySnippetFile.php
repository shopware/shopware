<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testEmptyList;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class EmptySnippetFile implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'empty';
    }

    public function getPath(): string
    {
        return __DIR__ . '/empty.json';
    }

    public function getIso(): string
    {
        return 'em_TY';
    }

    public function getAuthor(): string
    {
        return 'empty';
    }

    public function isBase(): bool
    {
        return true;
    }
}
