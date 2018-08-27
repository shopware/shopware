<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Api;

use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Api\Response\Type\JsonFactoryBase;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\SourceContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonApiType extends JsonFactoryBase
{
    /**
     * @var JsonApiEncoder
     */
    private $serializer;

    public function __construct(JsonApiEncoder $serializer)
    {
        $this->serializer = $serializer;
    }

    public function supports(string $contentType, string $origin): bool
    {
        return $contentType === 'application/vnd.api+json' && $origin === SourceContext::ORIGIN_API;
    }

    public function createDetailResponse(Entity $entity, string $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        $headers = [];

        $entityBaseUrl = $this->getEntityBaseUrl($request, $definition);
        if ($setLocationHeader) {
            $headers['Location'] = $entityBaseUrl . '/' . $entity->getId();
        }

        $rootNode = [
            'links' => [
                'self' => $request->getUri(),
            ],
        ];

        $response = $this->serializer->encode(
            $definition, $entity, $this->getApiBaseUrl($request), $rootNode
        );

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

        $rootNode['meta'] = [
            'fetchCount' => $searchResult->getCriteria()->fetchCount(),
            'total' => $searchResult->getTotal(),
        ];

        $aggregations = [];
        foreach ($searchResult->getAggregations() as $aggregation) {
            $aggregations[$aggregation->getName()] = $aggregation->getResult();
        }

        $rootNode['aggregations'] = $aggregations;

        $response = $this->serializer->encode(
            $definition, $searchResult, $this->getApiBaseUrl($request), $rootNode
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, [], true);
    }

    protected function getApiBaseUrl(Request $request): string
    {
        $versionPart = $this->getVersion($request) ? ('/v' . $this->getVersion($request)) : '';

        return $this->getBaseUrl($request) . '/api' . $versionPart;
    }
}
