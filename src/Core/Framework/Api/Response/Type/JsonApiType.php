<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type;

use Shopware\Core\Framework\Api\Context\RestContext;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Api\Response\ResponseTypeInterface;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class JsonApiType implements ResponseTypeInterface
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
        return $contentType === 'application/vnd.api+json';
    }

    public function createDetailResponse(Entity $entity, string $definition, RestContext $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        $baseUrl = $this->getBaseUrl($context);

        if ($setLocationHeader) {
            /* @var string|EntityDefinition $definition */
            $headers['Location'] = $baseUrl . '/api/v' . $context->getVersion() . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getId();
        }

        $rootNode = [
            'links' => [
                'self' => $baseUrl . $context->getRequest()->getPathInfo(),
            ],
        ];

        $response = $this->serializer->serialize(
            $entity,
            'jsonapi',
            [
                'uri' => $baseUrl . '/api/v' . $context->getVersion(),
                'data' => $rootNode,
                'definition' => $definition,
                'basic' => false,
            ]
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, $headers, true);
    }

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, RestContext $context): Response
    {
        $baseUrl = $this->getBaseUrl($context);

        $uri = $baseUrl . $context->getRequest()->getPathInfo();

        $rootNode = [
            'links' => $this->createPaginationLinks($searchResult, $uri, $context->getRequest()->query->all()),
        ];

        $rootNode['links']['self'] = $context->getRequest()->getUri();

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

        $response = $this->serializer->serialize(
            $searchResult,
            'jsonapi',
            [
                'uri' => $baseUrl . '/api/v' . $context->getVersion(),
                'data' => $rootNode,
                'definition' => $definition,
                'basic' => true,
            ]
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, [], true);
    }

    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response
    {
        /** @var string|EntityDefinition $definition */
        $headers = [
            'Location' => $this->getBaseUrl($context) . '/api/v' . $context->getVersion() . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $id,
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
