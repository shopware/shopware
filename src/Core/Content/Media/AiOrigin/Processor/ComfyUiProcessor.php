<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Processor;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * Recognises the workflow that ComfyUI embeds: the PNG text chunks `prompt` (API graph) and `workflow` (UI graph),
 * or for WebP the EXIF `Make`/`Model` values prefixed with `workflow:` and `prompt:`.
 * A workflow can start from a photo, but that is not visible without interpreting the graph, so the result is always "generated".
 *
 * @see https://docs.comfy.org/development/api-development/workflow-metadata
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class ComfyUiProcessor implements AiOriginProcessorInterface
{
    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        $candidates = [
            $provenance->textChunks['prompt'] ?? '',
            $provenance->textChunks['workflow'] ?? '',
            preg_replace('/^(?:prompt|workflow):/', '', $provenance->exif['Make'] ?? '') ?? '',
            preg_replace('/^(?:prompt|workflow):/', '', $provenance->exif['Model'] ?? '') ?? '',
        ];

        foreach ($candidates as $candidate) {
            if ($this->isComfyGraph($candidate)) {
                return MediaAiOrigin::Generated;
            }
        }

        return null;
    }

    private function isComfyGraph(string $json): bool
    {
        try {
            $graph = json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (!\is_array($graph)) {
            return false;
        }

        // UI workflow format
        if (\is_array($graph['nodes'] ?? null)) {
            return true;
        }

        // API prompt format: node id => {class_type, inputs}
        foreach ($graph as $node) {
            if (\is_array($node) && \is_string($node['class_type'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
