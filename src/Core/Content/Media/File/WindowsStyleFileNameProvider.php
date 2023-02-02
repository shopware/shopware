<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaCollection;

class WindowsStyleFileNameProvider extends FileNameProvider
{
    protected function getNextFileName(string $originalFileName, MediaCollection $relatedMedia, int $iteration): string
    {
        $suffix = $iteration === 0 ? '' : "_($iteration)";

        return $originalFileName . $suffix;
    }
}
