<?php declare(strict_types=1);

namespace Shopware\Rest\Response\Type;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Rest\Response\ResponseTypeInterface;
use Shopware\Rest\RestContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class JsonType implements ResponseTypeInterface
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    public function supportsContentType(string $contentType): bool
    {
        return $contentType === 'application/json';
    }

    /**
     * @param Entity                  $entity
     * @param string|EntityDefinition $definition
     * @param RestContext             $context
     * @param bool                    $setLocationHeader
     *
     * @return Response
     */
    public function createDetailResponse(Entity $entity, string $definition, RestContext $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        if ($setLocationHeader) {
            $headers['Location'] = $this->getBaseUrl($context) . '/api/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getId();
        }

        $decoded = $this->serializer->normalize($entity);

        $response = [
            'data' => $this->format($decoded),
        ];

        return new JsonResponse($response);
    }

    public function createListingResponse(SearchResultInterface $searchResult, string $definition, RestContext $context): Response
    {
        $decoded = $this->serializer->normalize($searchResult);

        $response = [
            'total' => $decoded['total'],
            'data' => $this->format($decoded),
        ];

        return new JsonResponse($response);
    }

    public function createErrorResponse(Request $request, \Throwable $exception, int $statusCode = 400): Response
    {
        $response = [
            'error' => [
                [
                    'status' => (string) $statusCode,
                    'title' => Response::$statusTexts[$statusCode],
                    'detail' => $exception->getMessage(),
                ],
            ],
        ];

        return new JsonResponse($response);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param string                  $id
     * @param RestContext             $context
     *
     * @return Response
     */
    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response
    {
        $headers = [
            'Location' => $this->getBaseUrl($context) . '/api/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $id,
        ];

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    private function format($decoded)
    {
        if (!\is_array($decoded) || empty($decoded)) {
            return $decoded;
        }

        if (array_key_exists('_class', $decoded) && preg_match('/(Collection|SearchResult)$/', $decoded['_class'])) {
            $elements = [];
            foreach ($decoded['elements'] as $element) {
                $elements[] = $this->format($element);
            }

            return $elements;
        }

        unset($decoded['_class']);

        foreach ($decoded as $key => $value) {
            $decoded[$key] = $this->format($value);
        }

        return $decoded;
    }

    /**
     * @param RestContext $context
     *
     * @return string
     */
    private function getBaseUrl(RestContext $context): string
    {
        return $context->getRequest()->getSchemeAndHttpHost() . $context->getRequest()->getBasePath();
    }

    private function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return ltrim(strtolower(preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }
}
