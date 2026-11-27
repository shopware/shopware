<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Recognises Midjourney downloads by the "Job ID" that Midjourney appends to the prompt in the XMP description.
 * Current downloads also carry the IPTC digital source type, so this covers files that lost only that property.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class MidjourneyProcessor implements AiOriginProcessorInterface
{
    private const JOB_ID_PATTERN = '/Job ID: [0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        foreach ($provenance->xmpValues(ImageProvenance::DUBLIN_CORE_NAMESPACE, 'description') as $description) {
            if (preg_match(self::JOB_ID_PATTERN, $description) === 1) {
                return MediaAiOrigin::Generated;
            }
        }

        return null;
    }
}
