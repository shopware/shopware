<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Immutable configuration for content output, enabling partial tree rendering.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class RenderingContext
{
    public function __construct(
        public ?string $targetElementId = null,
        public bool $includeAncestors = true,
        public bool $includeDescendants = true,
    ) {
    }

    public static function fromRequest(Request $request): RenderingContext
    {
        $elementId = $request->query->get('elementId');

        if ($elementId === null || $elementId === '') {
            return new self(null);
        }

        if (!\is_string($elementId)) {
            throw ContentSystemException::invalidElementId();
        }

        return new self($elementId, includeAncestors: false);
    }
}
