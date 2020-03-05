<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @Decoratable()
 */
interface FileSaverInterface
{
    public function persistFileToMedia(
        MediaFile $mediaFile,
        string $destination,
        string $mediaId,
        Context $context
    ): void;

    public function renameMedia(string $mediaId, string $destination, Context $context): void;
}
