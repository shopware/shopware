<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Storefront;

use Shopware\Core\Framework\Api\Response\Type\Api;
use Shopware\Core\Framework\Api\Serializer\JsonStorefrontApiEncoder;
use Shopware\Core\Framework\SourceContext;
use Symfony\Component\HttpFoundation\Request;

class JsonApiType extends Api\JsonApiType
{
    public function __construct(JsonStorefrontApiEncoder $serializer)
    {
        parent::__construct($serializer);
    }

    public function supports(string $contentType, string $origin): bool
    {
        return $contentType === 'application/vnd.api+json' && $origin === SourceContext::ORIGIN_STOREFRONT_API;
    }

    protected function getApiBaseUrl(Request $request): string
    {
        $versionPart = $this->getVersion($request) ? ('/v' . $this->getVersion($request)) : '';

        return $this->getBaseUrl($request) . '/storefront-api' . $versionPart;
    }
}
