<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
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

    /**
     * @var SnippetServiceInterface
     */
    private $snippetService;

    public function __construct(RequestCriteriaBuilder $criteriaBuilder, SnippetServiceInterface $snippetService)
    {
        $this->criteriaBuilder = $criteriaBuilder;
        $this->snippetService = $snippetService;
    }

    /**
     * @Route("/api/v{version}/_action/snippet-set", name="api.action.snippet-set.getList", methods={"POST"})
     */
    public function getList(Request $request, Context $context): Response
    {
        $criteria = new Criteria();
        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            SnippetDefinition::class,
            Context::createDefaultContext()
        );

        return new JsonResponse(
            $this->snippetService->getList($criteria, $context)
        );
    }

    /**
     * @Route("/api/{version}/_action/snippet", name="api.action.snippet.get", methods={"POST"})
     */
    public function getByKey(Request $request, Context $context): Response
    {
        $criteria = new Criteria();
        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            SnippetDefinition::class,
            Context::createDefaultContext()
        );

        $translationKey = $request->request->get('translationKey');

        $response = $this->snippetService->getList($criteria, $context);
        $response = $translationKey !== null ? $response['data'][$translationKey] : false;

        return new JsonResponse($response);
    }
}
