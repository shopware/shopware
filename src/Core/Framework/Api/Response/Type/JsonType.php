<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type;

use Shopware\Core\Framework\Api\Context\RestContext;
use Shopware\Core\Framework\Api\Response\ResponseTypeInterface;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
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

    /**
     * @var bool
     */
    private $debug;

    public function __construct(Serializer $serializer, bool $debug)
    {
        $this->serializer = $serializer;
        $this->debug = $debug;
    }

    public function supportsContentType(string $contentType): bool
    {
        return $contentType === 'application/json';
    }

    public function createDetailResponse(Entity $entity, string $definition, RestContext $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        if ($setLocationHeader) {
            /* @var string|EntityDefinition $definition */
            $headers['Location'] = $this->getBaseUrl($context) . '/api/v' . $context->getVersion() . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getId();
        }

        $decoded = $this->serializer->normalize($entity);

        $response = [
            'data' => $this->format($decoded),
        ];

        return new JsonResponse($response);
    }

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, RestContext $context): Response
    {
        $decoded = $this->serializer->normalize($searchResult);

        $response = [
            'total' => $decoded['total'],
            'data' => $this->format($decoded),
        ];

        if ($searchResult && $searchResult->getAggregations()) {
            $aggregations = [];
            foreach ($searchResult->getAggregations() as $aggregation) {
                $aggregations[$aggregation->getName()] = $aggregation->getResult();
            }

            $response['aggregations'] = $aggregations;
        }

        return new JsonResponse($response);
    }

    public function createErrorResponse(Request $request, \Throwable $exception, int $statusCode = 400): Response
    {
        $error = [
            'status' => (string) $statusCode,
            'title' => Response::$statusTexts[$statusCode] ?? 'unknown status',
            'detail' => $exception->getMessage(),
        ];

        if ($this->debug) {
            $error['trace'] = $exception->getTraceAsString();
        }

        return new JsonResponse(['errors' => [$error]]);
    }

    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response
    {
        /** @var string|EntityDefinition $definition */
        $headers = [
            'Location' => $this->getBaseUrl($context) . '/api/v' . $context->getVersion() . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $id,
        ];

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
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
