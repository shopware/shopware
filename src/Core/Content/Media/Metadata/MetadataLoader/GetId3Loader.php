<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Metadata\MetadataLoader;

use getID3;
use getid3_exception;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\Type\ImageMetadata;
use Shopware\Core\Content\Media\Metadata\Type\MetadataType;
use Shopware\Core\Content\Media\Metadata\Type\VideoMetadata;

class GetId3Loader implements MetadataLoaderInterface
{
    /**
     * @var getID3
     */
    private $getId3;

    public function extractMetadata(string $filePath): array
    {
        try {
            $metadata = $this->getGetId3()
                ->analyze($filePath);
        } catch (getid3_exception $e) {
            throw new CanNotLoadMetadataException('Unable to use getId3 in this environment', 0, $e);
        }

        if (isset($metadata['error'])) {
            throw new CanNotLoadMetadataException(sprintf('File %s is not supported by library getId3', $filePath));
        }

        return $metadata;
    }

    public function enhanceTypeObject(MetadataType $metadataType, array $rawMetadata): void
    {
        if ($metadataType instanceof ImageMetadata) {
            $metadataType->setHeight($rawMetadata['video']['resolution_x']);
            $metadataType->setWidth($rawMetadata['video']['resolution_y']);
        }

        if ($metadataType instanceof VideoMetadata) {
            $metadataType->setFrameRate($rawMetadata['video']['frame_rate']);
        }
    }

    /**
     * @throws getid3_exception
     */
    private function getGetId3(): getID3
    {
        if (!$this->getId3) {
            $this->getId3 = new getID3();
        }

        return $this->getId3;
    }
}
