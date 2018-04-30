<?php declare(strict_types=1);

namespace Shopware\Rest\Response;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Rest\Context\RestContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ResponseFactory
{
    public const DEFAULT_RESPONSE_TYPE = 'application/vnd.api+json';

    /**
     * @var ResponseTypeInterface[]
     */
    private $responseFactories;

    public function __construct(iterable $responseFactories)
    {
        $this->responseFactories = $responseFactories;
    }

    public function createDetailResponse(Entity $entity, string $definition, RestContext $context, bool $setLocationHeader = false): Response
    {
        return $this->getType($context->getRequest()->getAcceptableContentTypes())
            ->createDetailResponse($entity, $definition, $context, $setLocationHeader);
    }

    public function createListingResponse(SearchResultInterface $searchResult, string $definition, RestContext $context): Response
    {
        return $this->getType($context->getRequest()->getAcceptableContentTypes())
            ->createListingResponse($searchResult, $definition, $context);
    }

    public function createErrorResponse(Request $request, \Throwable $exception, int $statusCode = 400): Response
    {
        return $this->getType($request->getAcceptableContentTypes())
            ->createErrorResponse($request, $exception, $statusCode);
    }

    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response
    {
        return $this->getType($context->getRequest()->getAcceptableContentTypes())
            ->createRedirectResponse($definition, $id, $context);
    }

    /**
     * @param string[] $contentTypes
     *
     * @return ResponseTypeInterface
     */
    private function getType(array $contentTypes): ResponseTypeInterface
    {
        if (\in_array('*/*', $contentTypes, true)) {
            $contentTypes[] = self::DEFAULT_RESPONSE_TYPE;
        }

        foreach ($contentTypes as $contentType) {
            foreach ($this->responseFactories as $factory) {
                if ($factory->supportsContentType($contentType)) {
                    return $factory;
                }
            }
        }

        throw new UnsupportedMediaTypeHttpException(sprintf('Can not response with any of the provided accept header content types. (%s)', implode(', ', $contentTypes)));
    }
}
