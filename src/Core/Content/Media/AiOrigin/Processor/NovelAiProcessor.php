<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Recognises NovelAI images: the PNG text chunk `Software` names NovelAI,
 * or `Comment` holds the generation parameters as JSON.
 * The additional "stealth" data in the alpha channel would need pixel decoding and is not read.
 *
 * @see https://github.com/NovelAI/novelai-image-metadata
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class NovelAiProcessor implements AiOriginProcessorInterface
{
    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        if (str_starts_with($provenance->textChunks['Software'] ?? '', 'NovelAI')) {
            return MediaAiOrigin::Generated;
        }

        try {
            $parameters = json_decode($provenance->textChunks['Comment'] ?? '', true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $isGenerationParameters = \is_array($parameters)
            && isset($parameters['steps'])
            && (isset($parameters['sampler']) || isset($parameters['n_samples']));

        return $isGenerationParameters ? MediaAiOrigin::Generated : null;
    }
}
