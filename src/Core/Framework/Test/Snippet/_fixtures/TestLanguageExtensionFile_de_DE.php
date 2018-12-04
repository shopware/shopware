<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures;

use Shopware\Core\Framework\Snippet\Files\LanguageFileInterface;

class TestLanguageExtensionFile_de_DE implements LanguageFileInterface
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

    public function isBase(): bool
    {
        return LanguageFileInterface::PLUGIN_LANGUAGE_EXTENSION_FILE;
    }
}
