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
     * @var SnippetServiceInterface
     */
    private $snippetService;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(SnippetServiceInterface $snippetService, RequestCriteriaBuilder $criteriaBuilder)
    {
        $this->snippetService = $snippetService;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @Route("/api/v{version}/_action/snippet-set", name="api.action.snippet-set.getList", methods={"POST"})
     */
    public function getList(Request $request): Response
    {
        $criteria = new Criteria();
        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            SnippetDefinition::class,
            Context::createDefaultContext()
        );

        return new JsonResponse($this->snippetService->getList($criteria));
    }

    /**
     * @Route("/api/{version}/_action/snippet", name="api.action.snippet.get", methods={"POST"})
     */
    public function getByKey(Request $request): Response
    {
        $criteria = new Criteria();
        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            SnippetDefinition::class,
            Context::createDefaultContext()
        );

        $translationKey = $request->request->get('translationKey');

        $response = $this->snippetService->getList($criteria);
        $response = $translationKey !== null ? $response['data'][$translationKey] : false;

        return new JsonResponse($response);
    }
}
