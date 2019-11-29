<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Translation\_fixtures;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_UnitTest implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.unitTest';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.unitTest.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
    }

    public function getAuthor(): string
    {
        return 'unitTest';
    }

    public function isBase(): bool
    {
        return false;
    }
}
