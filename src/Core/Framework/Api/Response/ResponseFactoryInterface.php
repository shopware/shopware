<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
interface ResponseFactoryInterface
{
    public function supports(string $contentType, ContextSource $origin): bool;

    public function createDetailResponse(Criteria $criteria, Entity $entity, EntityDefinition $definition, Request $request, Context $context, bool $setLocationHeader = false): Response;

    public function createListingResponse(Criteria $criteria, EntitySearchResult $searchResult, EntityDefinition $definition, Request $request, Context $context): Response;

    public function createRedirectResponse(EntityDefinition $definition, string $id, Request $request, Context $context): Response;
}
