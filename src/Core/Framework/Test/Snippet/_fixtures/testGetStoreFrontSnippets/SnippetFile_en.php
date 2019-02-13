<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetStoreFrontSnippets;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_en implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'snippets_en';
    }

    public function getPath(): string
    {
        return __DIR__ . '/en.json';
    }

    public function getIso(): string
    {
        return 'en_GB';
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
