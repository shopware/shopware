<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\TypeDetector;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class TypeDetector implements TypeDetectorInterface
{
    /**
     * @internal
     *
     * @param TypeDetectorInterface[] $typeDetector
     */
    public function __construct(private readonly iterable $typeDetector)
    {
    }

    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType = null): MediaType
    {
        $mediaType = null;
        foreach ($this->typeDetector as $typeDetector) {
            $mediaType = $typeDetector->detect($mediaFile, $mediaType);
        }

        return $mediaType;
    }
}
