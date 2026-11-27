<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Recognises one way of telling that an image was made or changed with generative AI.
 * Register an implementation with the `shopware.media.ai_origin.processor` tag.
 * Processors with a higher tag priority run first, and the first result that is not `null` wins.
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
interface AiOriginProcessorInterface
{
    /**
     * Returns `null` when this processor finds no signal it knows, so that the next processor can decide.
     */
    public function process(ImageProvenance $provenance): ?MediaAiOrigin;
}
