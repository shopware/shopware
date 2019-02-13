<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetStoreFrontSnippets;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_de implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'snippets_de';
    }

    public function getPath(): string
    {
        return __DIR__ . '/de.json';
    }

    public function getIso(): string
    {
        return 'de_DE';
    }

    public function getAuthor(): string
    {
        return 'unitTests';
    }

    public function isBase(): bool
    {
        return true;
    }
}
