<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class en_US_e1 implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'en_us_e1';
    }

    public function getPath(): string
    {
        return __DIR__ . '/.json';
    }

    public function getIso(): string
    {
        return 'en_US';
    }

    public function getAuthor(): string
    {
        return 'testUSe1';
    }

    public function isBase(): bool
    {
        return SnippetFileInterface::BASE_LANGUAGE_FILE;
    }
}
