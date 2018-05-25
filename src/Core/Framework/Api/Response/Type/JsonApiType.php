<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Response\Type;

use Shopware\Framework\Api\Context\RestContext;
use Shopware\Framework\Api\Exception\WriteStackHttpException;
use Shopware\Framework\Api\Response\JsonApiResponse;
use Shopware\Framework\Api\Response\ResponseTypeInterface;
use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Write\FieldException\InvalidFieldException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Serializer;

class JsonApiType implements ResponseTypeInterface
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

    public function createListingResponse(SearchResultInterface $searchResult, string $definition, RestContext $context): Response
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

        if ($searchResult && $searchResult->getAggregationResult()) {
            $aggregations = [];
            foreach ($searchResult->getAggregationResult()->getAggregations() as $aggregation) {
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

    public function createErrorResponse(Request $request, \Throwable $exception, int $statusCode = 400): Response
    {
        $errorData = [
            'errors' => $this->convertExceptionToError($exception),
        ];

        return new JsonApiResponse($errorData, $statusCode);
    }

    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response
    {
        /** @var string|EntityDefinition $definition */
        $headers = [
            'Location' => $this->getBaseUrl($context) . '/api/v' . $context->getVersion() . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $id,
        ];

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    private function createPaginationLinks(SearchResultInterface $searchResult, string $uri, array $parameters): array
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

    private function convertExceptionToError(\Throwable $exception): array
    {
        if ($exception instanceof WriteStackHttpException) {
            return $this->handleWriteStackException($exception);
        }

        $statusCode = 500;

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        $error = [
            'code' => (string) $exception->getCode(),
            'status' => (string) $statusCode,
            'title' => Response::$statusTexts[$statusCode] ?? 'unknown status',
            'detail' => $exception->getMessage(),
        ];

        if ($this->debug) {
            $error['trace'] = $exception->getTraceAsString();
        }

        // single exception (default)
        return [$error];
    }

    private function handleWriteStackException(WriteStackHttpException $exception): array
    {
        $errors = [];

        foreach ($exception->getExceptionStack()->getExceptions() as $innerException) {
            if ($innerException instanceof InvalidFieldException) {
                foreach ($innerException->getViolations() as $violation) {
                    $error = [
                        'code' => (string) $exception->getCode(),
                        'status' => (string) $exception->getStatusCode(),
                        'title' => $innerException->getConcern(),
                        'detail' => $violation->getMessage(),
                        'source' => ['pointer' => $innerException->getPath()],
                    ];

                    if ($this->debug) {
                        $error['trace'] = $innerException->getTraceAsString();
                    }

                    $errors[] = $error;
                }

                continue;
            }

            $error = [
                'code' => (string) $exception->getCode(),
                'status' => (string) $exception->getStatusCode(),
                'title' => $innerException->getConcern(),
                'detail' => $innerException->getMessage(),
                'source' => ['pointer' => $innerException->getPath()],
            ];

            if ($this->debug) {
                $error['trace'] = $innerException->getTraceAsString();
            }

            $errors[] = $error;
        }

        return $errors;
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
