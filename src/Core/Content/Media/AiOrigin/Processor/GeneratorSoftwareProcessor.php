<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Last resort: the creating software is a tool that can only generate images.
 * It reads EXIF `Software`, the PNG text chunk `Software` and XMP `xmp:CreatorTool`.
 * Editors such as Photoshop are not listed, because their name says nothing about AI use.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class GeneratorSoftwareProcessor implements AiOriginProcessorInterface
{
    private const GENERATOR_PATTERN = '/\b(?:Midjourney|DALL[-·]?E|Image Playground|NovelAI|Stable Diffusion)\b/iu';

    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        $names = [
            $provenance->exif['Software'] ?? '',
            $provenance->textChunks['Software'] ?? '',
            ...$provenance->xmpValues(ImageProvenance::XMP_NAMESPACE, 'CreatorTool'),
        ];

        foreach ($names as $name) {
            if (preg_match(self::GENERATOR_PATTERN, $name) === 1) {
                return MediaAiOrigin::Generated;
            }
        }

        return null;
    }
}
