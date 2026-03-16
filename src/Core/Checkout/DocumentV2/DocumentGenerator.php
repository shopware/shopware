<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[Package('TODO')]
class DocumentGenerator
{
    /**
     * Lookup table for document renderer classes by document type and format.
     *
     * @var array<string, array<string, AbstractDocumentRenderer>>
     */
    private array $renderers = [];

    /**
     * @param iterable<AbstractDocumentRenderer> $renderers
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        iterable $renderers,
        private readonly EntityRepository $orderRepository,
    ) {
        // todo: build this lookup table in a custom compiler pass / service locator,
        // based on the tag attributes in the container
        foreach ($renderers as $renderer) {
            foreach ($renderer->getDocumentTypes() as $docType) {
                $this->renderers[$docType][$renderer->getFormat()] = $renderer;
            }
        }
    }

    /**
     * @param list<string> $formats
     */
    public function generate(string $orderId, string $docType, array $formats, Context $context): void
    {
        $renderers = $this->renderers[$docType] ?? [];
        if (empty($renderers)) {
            // todo: error handling
            throw new \RuntimeException('No renderers found for document type "' . $docType . '"');
        }

        $neededFormats = $this->resolveNeededFormats($renderers, $formats);
        $orderedRenderers = $this->topologicalSortRenderers($renderers, $neededFormats);

        $orderCriteria = new Criteria([$orderId]);
        foreach ($orderedRenderers as $renderer) {
            $renderer->enrichOrderCriteria($docType, $orderCriteria);
        }

        // generate document contents
        $generationContext = $this->getDocumentGenerationContext($orderCriteria, $docType, $context);
        $renderState = new RenderState();
        foreach ($orderedRenderers as $renderer) {
            echo 'rendering ' . $renderer->getFormat() . \PHP_EOL;

            $renderResult = $renderer->renderToString($generationContext, $renderState);
            $renderState->setRenderedContent($renderer->getFormat(), $renderResult);

            echo 'content: ' . $renderResult->documentContent . \PHP_EOL;
        }

        // persist content to files for selected formats
        foreach ($formats as $format) {
            $renderer = $renderers[$format];
            $renderResult = $renderState->getRenderedContent($format);

            echo 'PERSIST to file: ' . $format . ' content: ' . $renderResult->documentContent . \PHP_EOL;
            $renderer->persistToFile($generationContext, $renderResult);
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

    private function getDocumentGenerationContext(Criteria $orderCriteria, string $docType, Context $context): DocumentGenerationContext
    {
        $order = $this->orderRepository->search($orderCriteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            // todo: error handling
            throw new \RuntimeException('Order not found');
        }

        return new DocumentGenerationContext($order, $docType, $this->getDocumentConfig($docType, $context));
    }

    private function getDocumentConfig(string $docType, Context $context): DocumentConfig
    {
        // todo: build actual config
        return new DocumentConfig(
            'prefix',
            'suffix',
        );
    }
}
