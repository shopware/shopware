<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
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

    public function createDetailResponse(Entity $entity, string $definition, Request $request, Context $context, bool $setLocationHeader = false): Response
    {
        return $this->getType($request->getAcceptableContentTypes())
            ->createDetailResponse($entity, $definition, $request, $context, $setLocationHeader);
    }

    public function createListingResponse(EntitySearchResult $searchResult, string $definition, Request $request, Context $context): Response
    {
        return $this->getType($request->getAcceptableContentTypes())
            ->createListingResponse($searchResult, $definition, $request, $context);
    }

    public function createRedirectResponse(string $definition, string $id, Request $request, Context $context): Response
    {
        return $this->getType($request->getAcceptableContentTypes())
            ->createRedirectResponse($definition, $id, $request, $context);
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
