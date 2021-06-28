<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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
     * @Since("6.0.0.0")
     * @Route("/api/_action/snippet-set", name="api.action.snippet-set.getList", methods={"POST"})
     */
    public function getList(Request $request, Context $context): Response
    {
        return new JsonResponse(
            $this->snippetService->getList(
                $request->request->getInt('page', 1),
                $request->request->getInt('limit', 25),
                $context,
                $request->request->all('filters'),
                $request->request->all('sort')
            )
        );
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/snippet/filter", name="api.action.snippet.get.filter", methods={"GET"})
     */
    public function getFilterItems(Context $context): Response
    {
        $filter = $this->snippetService->getRegionFilterItems($context);

        return new JsonResponse([
            'total' => \count($filter),
            'data' => $filter,
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/snippet-set/baseFile", name="api.action.snippet-set.base-file", methods={"GET"})
     */
    public function getBaseFiles(): Response
    {
        $files = $this->snippetFileCollection->getFilesArray();

        return new JsonResponse([
            'items' => $files,
            'total' => \count($files),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/snippet-set/author", name="api.action.snippet-set.author", methods={"GET"})
     */
    public function getAuthors(Context $context): Response
    {
        $authors = $this->snippetService->getAuthors($context);

        return new JsonResponse([
            'total' => \count($authors),
            'data' => $authors,
        ]);
    }
}
