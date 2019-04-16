<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelApiController
{
    /**
     * @var SalesChannelDefinitionRegistry
     */
    private $registry;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(SalesChannelDefinitionRegistry $registry, RequestCriteriaBuilder $criteriaBuilder)
    {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    public function search(Request $request, SalesChannelContext $context, string $entity): Response
    {
        /** @var SalesChannelRepository $repository */
        $repository = $this->registry->getRepository($entity);

        /** @var SalesChannelDefinitionInterface|string $definition */
        $definition = $this->registry->get($entity);

        $criteria = new Criteria();
        $this->criteriaBuilder->handleRequest($request, $criteria, $definition, $context->getContext());

        $definition::processApiCriteria($criteria, $context);

        $result = $repository->search($criteria, $context);

        return new JsonResponse($result);
    }
}
