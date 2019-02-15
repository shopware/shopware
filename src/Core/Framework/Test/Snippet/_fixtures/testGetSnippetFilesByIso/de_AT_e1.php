<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class de_AT_e1 implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'de_at_e1';
    }

    public function getPath(): string
    {
        return __DIR__ . '/de_at_e1.json';
    }

    public function getIso(): string
    {
        return 'de_AT';
    }

    public function getAuthor(): string
    {
        return 'testATe1';
    }

    public function isBase(): bool
    {
        return true;
    }
}
