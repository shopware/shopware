<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type;

use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Api\Response\ResponseTypeInterface;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonApiType implements ResponseTypeInterface
{
    /**
     * @var JsonApiEncoder
     */
    private $serializer;

    public function __construct(JsonApiEncoder $serializer)
    {
        $this->serializer = $serializer;
    }

    public function supportsContentType(string $contentType): bool
    {
        return $contentType === 'application/vnd.api+json';
    }

    public function createDetailResponse(Entity $entity, string $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        $baseUrl = $this->getBaseUrl($request);

        if ($setLocationHeader) {
            /* @var string|EntityDefinition $definition */
            $headers['Location'] = $baseUrl . '/api/v' . $this->getVersion($request) . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getId();
        }

        $rootNode = [
            'links' => [
                'self' => $baseUrl . $request->getPathInfo(),
            ],
        ];

        $response = $this->serializer->encode(
            $definition,
            $entity,
            $context,
            $baseUrl . '/api/v' . $this->getVersion($request)
        );

        $response = json_decode($response, true);
        $response = array_merge($response, $rootNode);
        $response = json_encode($response);

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, $headers, true);
    }

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, Request $request, Context $context): Response
    {
        $baseUrl = $this->getBaseUrl($request);

        $uri = $baseUrl . $request->getPathInfo();

        $rootNode = [
            'links' => $this->createPaginationLinks($searchResult, $uri, $request->query->all()),
        ];

        $rootNode['links']['self'] = $request->getUri();

        if ($searchResult->getCriteria()->fetchCount()) {
            $rootNode['meta'] = [
                'total' => $searchResult->getTotal(),
            ];
        }

        if ($searchResult && $searchResult->getAggregations()) {
            $aggregations = [];
            foreach ($searchResult->getAggregations() as $aggregation) {
                $aggregations[$aggregation->getName()] = $aggregation->getResult();
            }

            $rootNode['aggregations'] = $aggregations;
        }

        $response = $this->serializer->encode(
            $definition,
            $searchResult,
            $context,
            $baseUrl . '/api/v' . $this->getVersion($request)
        );

        $response = json_decode($response, true);
        $response = array_merge($response, $rootNode);
        $response = json_encode($response);

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, [], true);
    }

    public function createRedirectResponse(string $definition, string $id, Request $request, Context $context): Response
    {
        /** @var string|EntityDefinition $definition */
        $headers = [
            'Location' => $this->getBaseUrl($request) . '/api/v' . $this->getVersion($request) . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $id,
        ];

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    private function createPaginationLinks(EntitySearchResult $searchResult, string $uri, array $parameters): array
    {
        $limit = $searchResult->getCriteria()->getLimit() ?? 0;
        $offset = $searchResult->getCriteria()->getOffset() ?? 0;

        if ($limit <= 0) {
            return [];
        }

        $pagination = [
            'first' => $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => 0,
                        'limit' => $limit,
                    ]]
                )
            ),
            'last' => $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => ceil($searchResult->getTotal() / $limit) * $limit - $limit,
                        'limit' => $limit,
                    ]]
                )
            ),
        ];

        if ($offset - $limit > 0) {
            $pagination['prev'] = $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => $offset - $limit,
                        'limit' => $limit,
                    ]]
                )
            );
        }

        if ($offset + $limit < $searchResult->getTotal()) {
            $pagination['next'] = $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => $offset + $limit,
                        'limit' => $limit,
                    ]]
                )
            );
        }

        return $pagination;
    }

    private function buildPaginationUrl(string $uri, array $parameters): string
    {
        return $uri . '?' . http_build_query($parameters);
    }

    private function getBaseUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . $request->getBasePath();
    }

    private function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return ltrim(strtolower(preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    private function getVersion(Request $request): int
    {
        return (int) $request->get('version');
    }
}
