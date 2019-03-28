<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files\en_EN;

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
        return 'Shopware';
    }

    public function isBase(): bool
    {
        return true;
    }
}
