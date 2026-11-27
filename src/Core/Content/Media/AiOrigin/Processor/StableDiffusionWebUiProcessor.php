<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Recognises the generation parameters that AUTOMATIC1111, Forge and SD.Next write:
 * the PNG text chunk `parameters`, or the EXIF `UserComment` for JPEG and WebP.
 * They do not tell txt2img from img2img or inpainting, so the result is always "generated".
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class StableDiffusionWebUiProcessor implements AiOriginProcessorInterface
{
    private const PARAMETERS_PATTERN = '/\bSteps: \d+, Sampler: /';

    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        foreach ([$provenance->textChunks['parameters'] ?? '', $provenance->exif['UserComment'] ?? ''] as $parameters) {
            if (preg_match(self::PARAMETERS_PATTERN, $parameters) === 1) {
                return MediaAiOrigin::Generated;
            }
        }

        return null;
    }
}
