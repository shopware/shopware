<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class en_US implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'en_us';
    }

    public function getPath(): string
    {
        return __DIR__ . '/en_us.json';
    }

    public function getIso(): string
    {
        return 'en_US';
    }

    public function isBase(): bool
    {
        return SnippetFileInterface::BASE_LANGUAGE_FILE;
    }

    public function getAuthor(): string
    {
        return 'unitTests';
    }
}
