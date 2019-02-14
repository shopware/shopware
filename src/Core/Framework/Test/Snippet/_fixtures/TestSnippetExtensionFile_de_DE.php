<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class TestSnippetExtensionFile_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'messages.de_DE.extension';
    }

    public function getPath(): string
    {
        return __DIR__ . '/messages.de_DE.json';
    }

    public function getIso(): string
    {
        return 'de_DE';
    }

    public function getAuthor(): string
    {
        return 'TestExtensionCompany';
    }

    public function isBase(): bool
    {
        return false;
    }
}
