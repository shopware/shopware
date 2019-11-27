<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

interface RequestTransformerInterface
{
    public function transform(Request $request): Request;

    /**
     * Return only attributes that should be inherited by subrequests
     */
    public function extractInheritableAttributes(Request $sourceRequest): array;
}
