<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Files\en_EN;

use Shopware\Core\Framework\Snippet\Files\LanguageFileInterface;

class LanguageFile_en_GB implements LanguageFileInterface
{
    public function getName(): string
    {
        return 'messages.en_GB';
    }

    public function getPath(): string
    {
        // todo@j.buecker use cdn dir here?
        return __DIR__ . '/messages.en_GB.json';
    }

    public function getIso(): string
    {
        return 'en_GB';
    }

    public function isBase(): bool
    {
        return LanguageFileInterface::BASE_LANGUAGE_FILE;
    }
}
