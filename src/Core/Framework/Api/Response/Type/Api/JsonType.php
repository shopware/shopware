<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Api;

use Shopware\Core\Framework\Api\Response\Type\JsonFactoryBase;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\ContextSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonType extends JsonFactoryBase
{
    /**
     * @var JsonEntityEncoder
     */
    private $encoder;

    public function __construct(JsonEntityEncoder $encoder)
    {
        $this->encoder = $encoder;
    }

    public function supports(string $contentType, ContextSource $origin): bool
    {
        return $contentType === 'application/json' && $origin instanceof AdminApiSource;
    }

    public function createDetailResponse(Entity $entity, EntityDefinition $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        if ($setLocationHeader) {
            $headers['Location'] = $this->getEntityBaseUrl($request, $definition) . '/' . $entity->getUniqueIdentifier();
        }

        $decoded = $this->encoder->encode($definition, $entity, $this->getApiBaseUrl($request));

        $response = [
            'data' => $decoded,
        ];

        return new JsonResponse($response, JsonResponse::HTTP_OK, $headers);
    }

    public function createListingResponse(EntitySearchResult $searchResult, EntityDefinition $definition, Request $request, Context $context): Response
    {
        $decoded = $this->encoder->encode($definition, $searchResult->getEntities(), $this->getApiBaseUrl($request));

        $response = [
            'total' => $searchResult->getTotal(),
            'data' => $decoded,
        ];

        $aggregations = [];
        foreach ($searchResult->getAggregations() as $aggregation) {
            $aggregations[$aggregation->getName()] = $aggregation;
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
