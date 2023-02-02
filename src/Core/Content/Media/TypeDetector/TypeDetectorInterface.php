<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\TypeDetector;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\MediaType;

interface TypeDetectorInterface
{
    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType;
}
