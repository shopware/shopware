<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files\de_DE;

use Shopware\Core\Framework\Snippet\Files\LanguageFileInterface;

class LanguageFile_de_DE implements LanguageFileInterface
{
    public function getName(): string
    {
        return 'messages.de_DE';
    }

    public function getPath(): string
    {
        // todo@j.buecker use cdn dir here?
        return __DIR__ . '/messages.de_DE.json';
    }

    public function getIso(): string
    {
        return 'de_DE';
    }

    public function isBase(): bool
    {
        return LanguageFileInterface::BASE_LANGUAGE_FILE;
    }
}
