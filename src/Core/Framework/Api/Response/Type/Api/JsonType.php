<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Api;

use Shopware\Core\Framework\Api\Response\Type\JsonFactoryBase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\ContextSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class JsonType extends JsonFactoryBase
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    public function supports(string $contentType, ContextSource $origin): bool
    {
        return $contentType === 'application/json' && $origin instanceof AdminApiSource;
    }

    public function createDetailResponse(Entity $entity, string $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        if ($setLocationHeader) {
            /* @var string|EntityDefinition $definition */
            $headers['Location'] = $this->getEntityBaseUrl($request, $definition) . '/' . $entity->getUniqueIdentifier();
        }

        $decoded = $this->serializer->normalize($entity);

        $response = [
            'data' => $decoded,
        ];

        return new JsonResponse($response, JsonResponse::HTTP_OK, $headers);
    }

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, Request $request, Context $context): Response
    {
        $decoded = $this->serializer->normalize($searchResult);

        $response = [
            'total' => $searchResult->getTotal(),
            'data' => $decoded,
        ];

        $aggregations = [];
        foreach ($searchResult->getAggregations() as $aggregation) {
            $aggregations[$aggregation->getName()] = $aggregation->getResult();
        }

        $response['aggregations'] = $aggregations;

        return new JsonResponse($response);
    }

    protected function getApiBaseUrl(Request $request): string
    {
        $versionPart = $this->getVersion($request) ? ('/v' . $this->getVersion($request)) : '';

        return $this->getBaseUrl($request) . '/api' . $versionPart;
    }
}
