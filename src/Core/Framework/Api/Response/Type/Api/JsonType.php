<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Response\Type\JsonFactoryBase;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class JsonType extends JsonFactoryBase
{
    /**
     * @internal
     */
    public function __construct(
        private readonly JsonEntityEncoder $encoder,
        private readonly StructEncoder $structEncoder
    ) {
    }

    public function supports(string $contentType, ContextSource $origin): bool
    {
        return $contentType === 'application/json' && $origin instanceof AdminApiSource;
    }

    public function createDetailResponse(Criteria $criteria, Entity $entity, EntityDefinition $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        if ($setLocationHeader) {
            $headers['Location'] = $this->getEntityBaseUrl($request, $definition) . '/' . $entity->getUniqueIdentifier();
        }

        $decoded = $this->encoder->encode(
            $criteria,
            $definition,
            $entity,
            $this->getApiBaseUrl($request)
        );

        $response = [
            'data' => $decoded,
        ];

        return new JsonResponse($response, JsonResponse::HTTP_OK, $headers);
    }

    public function createListingResponse(Criteria $criteria, EntitySearchResult $searchResult, EntityDefinition $definition, Request $request, Context $context): Response
    {
        $decoded = $this->encoder->encode(
            $criteria,
            $definition,
            $searchResult->getEntities(),
            $this->getApiBaseUrl($request)
        );

        $response = [
            'total' => $searchResult->getTotal(),
            'data' => $decoded,
        ];

        $fields = new ResponseFields(
            $request->get('includes', [])
        );

        $aggregations = [];
        foreach ($searchResult->getAggregations() as $aggregation) {
            $aggregations[$aggregation->getName()] = $this->structEncoder->encode(
                $aggregation,
                $fields
            );
        }

        $response['aggregations'] = $aggregations;

        return new JsonResponse($response);
    }

    protected function getApiBaseUrl(Request $request): string
    {
        return $this->getBaseUrl($request) . '/api';
    }
}
