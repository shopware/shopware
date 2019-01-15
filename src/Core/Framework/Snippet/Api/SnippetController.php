<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Snippet\Services\SnippetService;
use Shopware\Core\Framework\Snippet\Services\SnippetServiceInterface;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SnippetController extends AbstractController
{
    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(RequestCriteriaBuilder $criteriaBuilder)
    {
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/api/v{version}/snippet-set/getList", name="api.action.snippet.set.getList", methods={"POST", "GET"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function getList(Request $request): Response
    {
        /** @var SnippetServiceInterface $service */
        $service = $this->get(SnippetService::class);

        $criteria = new Criteria();
        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            SnippetDefinition::class,
            Context::createDefaultContext()
        );

        return new JsonResponse($service->getList($criteria));
    }
}
