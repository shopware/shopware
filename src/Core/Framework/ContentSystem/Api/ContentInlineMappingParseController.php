<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
        return new JsonResponse($this->parseText($payload));
    }

    #[Route(path: '/api/_action/content-system/inline-mapping/parse-batch', name: 'api.action.content_system.inline_mapping.parse_batch', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_POST])]
    public function parseBatch(
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentInlineMappingParseBatchRequest $payload,
    ): JsonResponse {
        $results = [];

        foreach ($payload->requests as $request) {
            try {
                $results[] = $this->parseText($request);
            } catch (ContentSystemException $exception) {
                if ($exception->getErrorCode() !== ContentSystemException::INLINE_MAPPING_INPUT_TOO_LARGE) {
                    throw $exception;
                }

                $results[] = [
                    'revision' => $request->revision,
                    'error' => [
                        'code' => $exception->getErrorCode(),
                        'message' => $exception->getMessage(),
                    ],
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * @return array{revision: int, tokens: list<array{path: string, match: string, canonicalToken: string|null, offset: int, insideMarkup: bool, malformed: bool}>}
     */
    private function parseText(ContentInlineMappingParseRequest $payload): array
    {
        $tokens = [];

        foreach ($this->parser->parse($payload->text) as $token) {
            $tokens[] = [
                'path' => $token->path,
                'match' => $token->match,
                'canonicalToken' => $token->malformed ? null : $this->parser->canonicalToken($token->path),
                'offset' => $token->offset,
                'insideMarkup' => $token->insideMarkup,
                'malformed' => $token->malformed,
            ];
        }

        return [
            'revision' => $payload->revision,
            'tokens' => $tokens,
        ];
    }
}
