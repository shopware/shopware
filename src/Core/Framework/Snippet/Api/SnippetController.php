<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Services\SnippetServiceInterface;
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
     * @var SnippetFileCollection
     */
    private $snippetFileCollection;

    public function __construct(
        SnippetServiceInterface $snippetService,
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
        $defaultFilters = [
            'isCustom' => false,
            'emptySnippets' => false,
            'term' => null,
            'namespaces' => [],
            'authors' => [],
            'translationKeys' => [],
        ];

        $filter = array_merge($defaultFilters, $request->get('filters', []));

        return new JsonResponse(
            $this->snippetService->getList(
                (int) $request->get('page', 1),
                (int) $request->get('limit', 25),
                $context,
                $filter
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
     * @Route("/api/{version}/_action/snippet-set/getBaseFiles", name="api.action.snippet-set.get-base-files", methods={"GET"})
     */
    public function getBaseFiles(): Response
    {
        $files = $this->snippetFileCollection->getFilesArray();

        return new JsonResponse([
            'items' => $files,
            'total' => count($files),
        ]);
    }
}
