<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testConvert;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'messages.en_GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/messages.en_GB.json';
    }

    public function getIso(): string
    {
        return 'en_GB';
    }

    public function getAuthor(): string
    {
        return 'unitTest';
    }

    public function isBase(): bool
    {
        return SnippetFileInterface::BASE_SNIPPET_FILE;
    }
}
