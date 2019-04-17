<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\SalesChannel;

use Shopware\Core\Framework\Api\Response\Type\Api;
use Shopware\Core\Framework\Api\Serializer\JsonSalesChannelApiEncoder;
use Shopware\Core\Framework\Context\ContextSource;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Symfony\Component\HttpFoundation\Request;

class JsonApiType extends Api\JsonApiType
{
    public function __construct(JsonSalesChannelApiEncoder $serializer)
    {
        parent::__construct($serializer);
    }

    public function supports(string $contentType, ContextSource $origin): bool
    {
        return $contentType === 'application/vnd.api+json' && $origin instanceof SalesChannelApiSource;
    }

    protected function getApiBaseUrl(Request $request): string
    {
        $versionPart = $this->getVersion($request) ? ('/v' . $this->getVersion($request)) : '';

        return $this->getBaseUrl($request) . '/sales-channel-api' . $versionPart;
    }
}
