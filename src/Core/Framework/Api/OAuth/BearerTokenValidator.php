<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - reason:class-hierarchy-change - class will be removed
 */
#[Package('core')]
readonly class BearerTokenValidator implements AuthorizationValidatorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private SymfonyBearerTokenValidator $bearerTokenValidator,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
    ) {
    }

    /**
     * @return ServerRequestInterface
     */
    public function validateAuthorization(ServerRequestInterface $request)
    {
        $sfRequest = $this->httpFoundationFactory->createRequest($request);

        $this->bearerTokenValidator->validateAuthorization($sfRequest);

        return $this->translateAttributes($sfRequest, $request);
    }

    public function translateAttributes(Request $sfRequest, ServerRequestInterface $request): ServerRequestInterface
    {
        foreach ($sfRequest->attributes->all() as $k => $v) {
            $request = $request->withAttribute($k, $v);
        }

        return $request;
    }
}
