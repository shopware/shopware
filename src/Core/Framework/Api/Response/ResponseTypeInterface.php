<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Response;

use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\Api\Context\RestContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ResponseTypeInterface
{
    public function supportsContentType(string $contentType): bool;

    public function createDetailResponse(Entity $entity, string $definition, RestContext $context, bool $setLocationHeader = false): Response;

    public function createListingResponse(SearchResultInterface $searchResult, string $definition, RestContext $context): Response;

    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response;

    public function createErrorResponse(Request $request, \Throwable $exception, int $statusCode = 400): Response;
}
