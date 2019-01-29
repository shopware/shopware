<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class de_AT implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'de_AT';
    }

    public function getPath(): string
    {
        return __DIR__ . '/de_AT.json';
    }

    public function getIso(): string
    {
        return 'de_AT';
    }

    public function isBase(): bool
    {
        return $this::BASE_LANGUAGE_FILE;
    }

    public function getAuthor(): string
    {
        return 'unitTests';
    }
}
