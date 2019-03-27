<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Storefront;

use Shopware\Core\Framework\Api\Response\Type\Api;
use Shopware\Core\Framework\Context\ContextSource;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Symfony\Component\HttpFoundation\Request;

class JsonType extends Api\JsonType
{
    public function supports(string $contentType, ContextSource $origin): bool
    {
        return $contentType === 'application/json' && $origin instanceof SalesChannelApiSource;
    }

    protected function getApiBaseUrl(Request $request): string
    {
        $versionPart = $this->getVersion($request) ? ('/v' . $this->getVersion($request)) : '';

        return $this->getBaseUrl($request) . '/storefront-api' . $versionPart;
    }
}
