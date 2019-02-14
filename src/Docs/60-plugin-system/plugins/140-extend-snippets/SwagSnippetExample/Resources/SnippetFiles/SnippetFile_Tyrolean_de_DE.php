<?php declare(strict_types=1);

namespace SwagSnippetExample\Resources\SnippetFiles;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_Tyrolean_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'messages.tyrolean.de_DE';
    }

    public function getPath(): string
    {
        return __DIR__ . '/messages.tyrolean.de_DE.json';
    }

    public function getIso(): string
    {
        return 'de_DE';
    }

    public function getAuthor(): string
    {
        return 'exampleShop24AT';
    }

    public function isBase(): bool
    {
        return false;
    }
}
