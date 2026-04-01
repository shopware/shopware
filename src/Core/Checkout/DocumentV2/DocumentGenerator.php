<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentGenerator
{
    /**
     * @var list<AbstractDocumentRenderer>
     */
    private array $renderers = [];

    /**
     * @param iterable<AbstractDocumentRenderer> $renderers
     */
    public function __construct(
        iterable $renderers,
        private readonly DocumentDataProviderCollector $dataProviderCollector,
    ) {
        foreach ($renderers as $renderer) {
            $this->renderers[] = $renderer;
        }
    }

    /**
     * for possible default doc types @see DocumentType
     *
     * @param list<string> $formats
     */
    public function generate(
        string $orderId,
        string $orderVersionId,
        string $docType,
        array $formats,
        Context $context,
        ?string $docNumber = null,
    ): void {
        // todo: return document entity instead of void

        if ($orderVersionId === Defaults::LIVE_VERSION) {
            // todo: error handling
            throw new \RuntimeException('Live version not supported here, use an existing one or create one first');
        }

        $renderers = [];
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($docType)) {
                $renderers[$renderer->getFormat()] = $renderer;
            }
        }

        if (empty($renderers)) {
            // todo: error handling
            throw new \RuntimeException('No renderers found for document type "' . $docType . '"');
        }

        $neededFormats = $this->resolveNeededFormats($renderers, $formats);
        $orderedRenderers = $this->topologicalSortRenderers($renderers, $neededFormats);

        $renderInput = $this->dataProviderCollector->collectFor(
            $docType,
            $orderId,
            $orderVersionId,
            $context,
            $docNumber,
        );
        $renderState = new RenderState();
        foreach ($orderedRenderers as $renderer) {
            echo 'rendering ' . $renderer->getFormat() . \PHP_EOL;

            $renderResult = $renderer->renderToString($renderInput, $renderState);
            $renderState->setRenderedContent($renderer->getFormat(), $renderResult);

            echo 'content: ' . $renderResult->documentContent . \PHP_EOL;
        }

        // persist content to files for selected formats
        foreach ($formats as $format) {
            $renderer = $renderers[$format];
            $renderResult = $renderState->getRenderedContent($format);
            if (!$renderResult instanceof RenderResult) {
                // todo: error handling
                throw new \RuntimeException('Missing renderer result for format: ' . $format);
            }

            echo 'PERSIST to file: ' . $format . ' content: ' . $renderResult->documentContent . \PHP_EOL;
            $renderer->persistToFile($renderInput, $renderResult);
        }
    }

    /**
     * @param array<string, AbstractDocumentRenderer> $renderers all renderers of the document type to work with
     * @param list<string> $formats all user-specified formats to generate
     *
     * @return list<string>
     */
    private function resolveNeededFormats(array $renderers, array $formats): array
    {
        $visited = [];
        $stack = $formats;

        while (!empty($stack)) {
            $format = \array_pop($stack);
            if (isset($visited[$format])) {
                continue;
            }

            if (!isset($renderers[$format])) {
                // todo: error handling
                throw new \RuntimeException('Missing renderer for format "' . $format . '"');
            }

            $visited[$format] = true;
            $stack = \array_merge($stack, $renderers[$format]->getDependencies());
        }

        return \array_keys($visited);
    }

    /**
     * Brings the renderers in the correct order to generate the all dependent formats where dependencies run first.
     *
     * Uses Kahn's algorithm to find a topological sort and throw an exception if the dependency tree is not acyclic.
     *
     * @param array<string, AbstractDocumentRenderer> $renderers all renderers of the document type to work with
     * @param list<string> $neededFormats all formats that are contained in the dependency tree
     *
     * @return list<AbstractDocumentRenderer>
     */
    private function topologicalSortRenderers(array $renderers, array $neededFormats): array
    {
        // number of incoming edges for each renderer, e.g., how often a renderer is referenced in the dependency tree
        $inDegree = [];
        foreach ($neededFormats as $format) {
            $inDegree[$format] = 0;
        }

        foreach ($neededFormats as $format) {
            $renderer = $renderers[$format];
            foreach ($renderer->getDependencies() as $dependency) {
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
        while (!empty($queue)) {
            $topFormat = \array_shift($queue);
            $sorted[] = $renderers[$topFormat];
            foreach ($renderers[$topFormat]->getDependencies() as $dependency) {
                --$inDegree[$dependency];
                if ($inDegree[$dependency] === 0) {
                    $queue[] = $dependency;
                }
            }
        }

        // cycle detection
        if (\count($sorted) !== \count($neededFormats)) {
            $remaining = [];
            foreach ($inDegree as $format => $deg) {
                if ($deg > 0) {
                    $remaining[] = $format;
                }
            }

            // todo: error handling
            throw new \RuntimeException('Dependency tree is not acyclic: ' . \implode(', ', $remaining));
        }

        return array_reverse($sorted);
    }
}
