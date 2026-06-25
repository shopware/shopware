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
     * @param list<string> $requestedFormats
     *
     * @return list<string>
     */
    public function resolve(string $documentType, array $requestedFormats): array
    {
        $renderers = $this->documentRendererRegistry->mapRenderersByFormat($documentType);

        $neededFormats = $this->resolveNeededFormats(
            $documentType,
            $renderers,
            $requestedFormats,
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
     * @param list<string> $requestedFormats
     *
     * @throws DocumentV2Exception
     *
     * @return list<string>
     */
    private function resolveNeededFormats(string $documentType, array $renderers, array $requestedFormats): array
    {
        $visited = [];
        $stack = $requestedFormats;

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
     * This uses Kahn's algorithm on a graph that points from a format to its prerequisites.
     *
     * @see https://www.geeksforgeeks.org/dsa/topological-sorting-indegree-based-solution/
     *
     * That produces the reverse of the execution order, so the sorted list is inverted before
     * it is returned.
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
                    throw DocumentV2Exception::missingRenderPlanDependency($dependency);
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
                    throw DocumentV2Exception::missingRenderPlanDependency($dependency);
                }

                --$inDegree[$dependency];

                if ($inDegree[$dependency] === 0) {
                    $queue[] = $dependency;
                }
            }
        }

        if (\count($sorted) !== \count($neededFormats)) {
            $remaining = [];

            foreach ($inDegree as $format => $degree) {
                if ($degree > 0) {
                    $remaining[] = $format;
                }
            }

            throw DocumentV2Exception::circularRenderDependency($remaining);
        }

        return array_reverse($sorted);
    }
}
