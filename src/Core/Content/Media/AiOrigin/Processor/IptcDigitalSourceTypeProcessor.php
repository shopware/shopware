<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Reads the IPTC Digital Source Type from XMP (`Iptc4xmpExt:DigitalSourceType`).
 * It is written by Google, Meta, Midjourney, Adobe and others, often next to a C2PA manifest.
 * Unlike C2PA it survives tools that keep XMP but drop unknown segments.
 *
 * Without an AI source type, the AI properties of IPTC Photo Metadata 2025.1 (the AI system, the prompt) still show
 * that AI was used. They do not say whether it generated or edited the image, so the image counts as generated.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class IptcDigitalSourceTypeProcessor implements AiOriginProcessorInterface
{
    private const AI_PROPERTIES = ['AISystemUsed', 'AISystemVersionUsed', 'AIPromptInformation', 'AIPromptWriterName'];

    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        $origins = array_map(
            MediaAiOrigin::fromDigitalSourceType(...),
            $provenance->xmpValues(ImageProvenance::IPTC_EXTENSION_NAMESPACE, 'DigitalSourceType'),
        );

        if (\in_array(MediaAiOrigin::Generated, $origins, true)) {
            return MediaAiOrigin::Generated;
        }

        if (\in_array(MediaAiOrigin::Modified, $origins, true)) {
            return MediaAiOrigin::Modified;
        }

        if ($this->hasAiProperty($provenance)) {
            return MediaAiOrigin::Generated;
        }

        return null;
    }

    private function hasAiProperty(ImageProvenance $provenance): bool
    {
        foreach (self::AI_PROPERTIES as $property) {
            if ($provenance->xmpValues(ImageProvenance::IPTC_EXTENSION_NAMESPACE, $property) !== []) {
                return true;
            }
        }

        return false;
    }
}
