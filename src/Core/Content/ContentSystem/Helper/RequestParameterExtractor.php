<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Helper;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts and validates request parameters for ContentSystem.
 *
 * @internal
 */
#[Package('discovery')]
class RequestParameterExtractor
{
    /**
     * Extracts and validates elementId query parameter for partial rendering.
     *
     * @throws ContentSystemException If elementId parameter is present but not a string
     */
    public function extractTargetElementId(Request $request): ?string
    {
        $targetElementId = $request->query->get('elementId');
        if ($targetElementId !== null && !\is_string($targetElementId)) {
            throw ContentSystemException::invalidElementId();
        }

        return $targetElementId;
    }
}
