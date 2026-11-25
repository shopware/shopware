<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class ResponseFactoryRegistry
{
    private const DEFAULT_RESPONSE_TYPE = 'application/json';

    // @deprecated tag:v6.8.0 - Remove the legacy JSON:API default.
    private const LEGACY_DEFAULT_RESPONSE_TYPE = 'application/vnd.api+json';

    /**
     * @var ResponseFactoryInterface[]
     */
    private readonly array $responseFactories;

    /**
     * @internal
     */
    public function __construct(ResponseFactoryInterface ...$responseFactories)
    {
        $this->responseFactories = $responseFactories;
    }

    public function getType(Request $request): ResponseFactoryInterface
    {
        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        $contentTypes = $request->getAcceptableContentTypes();
        if ($contentTypes === [] || \in_array('*/*', $contentTypes, true)) {
            if (Feature::isActive('v6.8.0.0')) {
                $contentTypes[] = self::DEFAULT_RESPONSE_TYPE;
            } else {
                $contentTypes[] = ($context->getSource() instanceof SalesChannelApiSource)
                    ? self::DEFAULT_RESPONSE_TYPE
                    : self::LEGACY_DEFAULT_RESPONSE_TYPE;
            }
        }

        foreach ($contentTypes as $contentType) {
            foreach ($this->responseFactories as $factory) {
                if ($factory->supports($contentType, $context->getSource())) {
                    return $factory;
                }
            }
        }

        throw ApiException::unsupportedResponseMediaTypes($contentTypes);
    }
}
