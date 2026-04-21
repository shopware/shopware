<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentDependencyResolver
{
    public function __construct(
        private DocumentRendererRegistry $documentRendererRegistry,
    ) {
    }

    /**
     * @param list<string> $formats
     *
     * @return list<string>
     */
    public function resolve(string $documentType, array $formats): array
    {
        $renderers = $this->documentRendererRegistry->mapRenderersByFormat($documentType);

        $neededFormats = $this->resolveNeededFormats(
            $documentType,
            $renderers,
            $formats,
        );

        return $this->sortFormats(
            $documentType,
            $renderers,
            $neededFormats,
        );
    }

    /**
     * @param array<string, AbstractDocumentRenderer> $renderers
     * @param list<string> $formats
     *
     * @return list<string>
     */
    private function resolveNeededFormats(string $documentType, array $renderers, array $formats): array
    {
        $visited = [];
        $stack = $formats;

        while ($stack !== []) {
            $format = array_pop($stack);

            if (isset($visited[$format])) {
                continue;
            }

            if (!isset($renderers[$format])) {
                throw DocumentV2Exception::rendererNotFound($format, $documentType);
            }

            $visited[$format] = true;

            $stack = array_merge($stack, $renderers[$format]->getDependencies());
        }

        return array_keys($visited);
    }

    /**
     * @param array<string, AbstractDocumentRenderer> $renderers
     * @param list<string> $neededFormats
     *
     * @return list<string>
     */
    private function sortFormats(string $documentType, array $renderers, array $neededFormats): array
    {
        $inDegree = [];

        foreach ($neededFormats as $format) {
            $inDegree[$format] = 0;
        }

        foreach ($neededFormats as $format) {
            foreach ($renderers[$format]->getDependencies() as $dependency) {
                if (!isset($renderers[$dependency])) {
                    throw DocumentV2Exception::rendererNotFound($dependency, $documentType);
                }

                if (!isset($inDegree[$dependency])) {
                    continue;
                }

                ++$inDegree[$dependency];
            }
        }

        $queue = [];

        foreach ($neededFormats as $format) {
            if ($inDegree[$format] === 0) {
                $queue[] = $format;
            }
        }

        $sorted = [];

        while ($queue !== []) {
            $topFormat = array_shift($queue);

            $sorted[] = $topFormat;

            foreach ($renderers[$topFormat]->getDependencies() as $dependency) {
                if (!isset($inDegree[$dependency])) {
                    continue;
                }

                --$inDegree[$dependency];

                if ($inDegree[$dependency] === 0) {
                    $queue[] = $dependency;
                }
            }
        }

        if (\count($sorted) !== \count($neededFormats)) {
            throw DocumentV2Exception::circularRenderDependency();
        }

        return array_reverse($sorted);
    }
}
