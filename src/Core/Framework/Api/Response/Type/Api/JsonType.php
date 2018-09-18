<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type\Api;

use Shopware\Core\Framework\Api\Response\Type\JsonFactoryBase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\SourceContext;
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

    public function supports(string $contentType, string $origin): bool
    {
        return $contentType === 'application/json' && $origin === SourceContext::ORIGIN_API;
    }

    public function createDetailResponse(Entity $entity, string $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        if ($setLocationHeader) {
            /* @var string|EntityDefinition $definition */
            $headers['Location'] = $this->getEntityBaseUrl($request, $definition) . '/' . $entity->getId();
        }

        $decoded = $this->serializer->normalize($entity);

        $response = [
            'data' => self::format($decoded),
        ];

        return new JsonResponse($response, JsonResponse::HTTP_OK, $headers);
    }

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, Request $request, Context $context): Response
    {
        $decoded = $this->serializer->normalize($searchResult);

        $response = [
            'total' => $decoded['total'],
            'data' => self::format($decoded),
        ];

        $aggregations = [];
        foreach ($searchResult->getAggregations() as $aggregation) {
            $aggregations[$aggregation->getName()] = $aggregation->getResult();
        }

        $response['aggregations'] = $aggregations;

        return new JsonResponse($response);
    }

    public static function format($decoded)
    {
        if (!\is_array($decoded) || empty($decoded)) {
            return $decoded;
        }

        if (array_key_exists('_class', $decoded) && preg_match('/(Collection|SearchResult)$/', $decoded['_class'])) {
            $elements = [];
            foreach ($decoded['elements'] as $element) {
                $elements[] = self::format($element);
            }

            return $elements;
        }

        unset($decoded['_class']);

        foreach ($decoded as $key => $value) {
            $decoded[$key] = self::format($value);
        }

        return $decoded;
    }

    protected function getApiBaseUrl(Request $request): string
    {
        $versionPart = $this->getVersion($request) ? ('/v' . $this->getVersion($request)) : '';

        return $this->getBaseUrl($request) . '/api' . $versionPart;
    }
}
