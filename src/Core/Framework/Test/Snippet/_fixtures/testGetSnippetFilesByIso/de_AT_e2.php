<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class de_AT_e2 implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'de_at_e2';
    }

    public function getPath(): string
    {
        return __DIR__ . '/de_at_e2.json';
    }

    public function getIso(): string
    {
        return 'de_AT';
    }

    public function getAuthor(): string
    {
        return 'testATe2';
    }

    public function isBase(): bool
    {
        return true;
    }
}
