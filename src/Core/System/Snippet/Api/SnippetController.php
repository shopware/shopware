<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class SnippetController extends AbstractController
{
    /**
     * @var SnippetService
     */
    private $snippetService;

    /**
     * @var SnippetFileCollection
     */
    private $snippetFileCollection;

    public function __construct(
        SnippetService $snippetService,
        SnippetFileCollection $snippetFileCollection
    ) {
        $this->snippetService = $snippetService;
        $this->snippetFileCollection = $snippetFileCollection;
    }

    /**
     * @Route("/api/v{version}/_action/snippet-set", name="api.action.snippet-set.getList", methods={"POST"})
     */
    public function getList(Request $request, Context $context): Response
    {
        return new JsonResponse(
            $this->snippetService->getList(
                (int) $request->request->get('page', 1),
                (int) $request->request->get('limit', 25),
                $context,
                $request->request->get('filters', []),
                $request->request->get('sort', [])
            )
        );
    }

    /**
     * @Route("/api/{version}/_action/snippet/filter", name="api.action.snippet.get.filter", methods={"GET"})
     */
    public function getFilterItems(Context $context): Response
    {
        $filter = $this->snippetService->getRegionFilterItems($context);

        return new JsonResponse([
            'total' => count($filter),
            'data' => $filter,
        ]);
    }

    /**
     * @Route("/api/{version}/_action/snippet-set/baseFile", name="api.action.snippet-set.base-file", methods={"GET"})
     */
    public function getBaseFiles(): Response
    {
        $files = $this->snippetFileCollection->getFilesArray();

        return new JsonResponse([
            'items' => $files,
            'total' => count($files),
        ]);
    }

    /**
     * @Route("/api/{version}/_action/snippet-set/author", name="api.action.snippet-set.author", methods={"GET"})
     */
    public function getAuthors(Context $context): Response
    {
        $authors = $this->snippetService->getAuthors($context);

        return new JsonResponse([
            'total' => count($authors),
            'data' => $authors,
        ]);
    }
}
