<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Expands requested formats to their full renderer dependency graph and returns the order in
 * which formats have to be rendered.
 *
 * Example:
 * If the caller requests `zugferd_embedded_pdf` and the registered renderers declare
 * `zugferd_embedded_pdf -> [pdf, zugferd_xml]` and `pdf -> [html]`,
 * the resolver returns `['html', 'zugferd_xml', 'pdf', 'zugferd_embedded_pdf']`.
 *
 * The resolved list can contain transient intermediate formats that are required during
 * rendering but are never persisted on their own.
 *
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
     * Builds the render plan for the requested output formats.
     *
     * The returned list is the render plan, not the persistence plan. Dependency-only formats
     * can appear here even if the caller never asked to store them.
     *
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
     * Collects all transitive dependencies of the requested formats.
     *
     * For example, requesting `pdf` also pulls in `html` when the PDF renderer declares
     * `html` as a dependency.
     *
     * @param array<string, AbstractDocumentRenderer> $renderers
     * @param list<string> $formats
     *
     * @throws DocumentV2Exception
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
     * Sorts all required formats so every dependency is rendered before the format that uses it.
     *
     * The dependency graph is built from a format to its prerequisites. This means a plain
     * topological sort produces the reverse of the execution order and has to be inverted
     * before it is returned.
     *
     * @param array<string, AbstractDocumentRenderer> $renderers
     * @param list<string> $neededFormats
     *
     * @throws DocumentV2Exception
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
