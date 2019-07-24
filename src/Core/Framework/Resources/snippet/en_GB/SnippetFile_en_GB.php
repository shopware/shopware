<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Resources\snippet\en_GB;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'core.en-GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/core.en-GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
    }

    public function getAuthor(): string
    {
        return 'Shopware';
    }

    public function isBase(): bool
    {
        return false;
    }
}
