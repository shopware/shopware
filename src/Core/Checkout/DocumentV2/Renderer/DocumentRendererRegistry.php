<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentRendererRegistry
{
    /**
     * @var array<string, AbstractDocumentRenderer>
     */
    private array $renderersByFormat;

    /**
     * @param iterable<AbstractDocumentRenderer> $documentRenderers
     */
    public function __construct(iterable $documentRenderers)
    {
        $renderersByFormat = [];

        foreach ($documentRenderers as $renderer) {
            $format = $renderer->getFormat();

            // tagged_iterator yields the highest-priority service first. the first per format wins
            if (isset($renderersByFormat[$format])) {
                continue;
            }

            $renderersByFormat[$format] = $renderer;
        }

        $this->renderersByFormat = $renderersByFormat;
    }

    /**
     * @throws DocumentV2Exception
     */
    public function getRenderer(string $format): AbstractDocumentRenderer
    {
        if (!isset($this->renderersByFormat[$format])) {
            throw DocumentV2Exception::rendererNotFound($format);
        }

        return $this->renderersByFormat[$format];
    }

    /**
     * @return array<string, AbstractDocumentRenderer>
     */
    public function getRenderers(): array
    {
        return $this->renderersByFormat;
    }

    public function getFileExtension(string $format): ?string
    {
        return ($this->renderersByFormat[$format] ?? null)?->getFileExtension();
    }
}
