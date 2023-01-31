<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

#[Package('core')]
class ResponseFactoryRegistry
{
    private const DEFAULT_RESPONSE_TYPE = 'application/vnd.api+json';
    private const SALES_CHANNEL_DEFAULT_RESPONSE_TYPE = 'application/json';

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
        if (\in_array('*/*', $contentTypes, true)) {
            $contentTypes[] = ($context->getSource() instanceof SalesChannelApiSource)
                ? self::SALES_CHANNEL_DEFAULT_RESPONSE_TYPE
                : self::DEFAULT_RESPONSE_TYPE;
        }

        foreach ($contentTypes as $contentType) {
            foreach ($this->responseFactories as $factory) {
                if ($factory->supports($contentType, $context->getSource())) {
                    return $factory;
                }
            }
        }

        throw new UnsupportedMediaTypeHttpException(sprintf('All provided media types are unsupported. (%s)', implode(', ', $contentTypes)));
    }
}
