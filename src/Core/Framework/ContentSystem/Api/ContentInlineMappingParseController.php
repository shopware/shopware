<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Exposes the server-owned inline mapping syntax to the Administration editor.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentInlineMappingParseController
{
    public function __construct(
        private readonly InlineMappingTokenParser $parser,
    ) {
    }

    #[Route(path: '/api/_action/content-system/inline-mapping/parse', name: 'api.action.content_system.inline_mapping.parse', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function parse(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentInlineMappingParseRequest $payload,
    ): JsonResponse {
        $tokens = [];

        foreach ($this->parser->parse($payload->text) as $token) {
            $tokens[] = [
                'path' => $token->path,
                'match' => $token->match,
                'canonicalToken' => $this->parser->canonicalToken($token->path),
                'offset' => $token->offset,
                'insideMarkup' => $token->insideMarkup,
                'malformed' => $token->malformed,
            ];
        }

        return new JsonResponse([
            'revision' => $payload->revision,
            'tokens' => $tokens,
        ]);
    }
}
