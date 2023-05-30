<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidLimitQueryException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class SnippetController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SnippetService $snippetService,
        private readonly SnippetFileCollection $snippetFileCollection
    ) {
    }

    #[Route(path: '/api/_action/snippet-set', name: 'api.action.snippet-set.getList', methods: ['POST'])]
    public function getList(Request $request, Context $context): Response
    {
        $limit = $request->request->getInt('limit', 25);

        if ($limit < 1) {
            throw new InvalidLimitQueryException($limit);
        }

        return new JsonResponse(
            $this->snippetService->getList(
                $request->request->getInt('page', 1),
                $limit,
                $context,
                $request->request->all('filters'),
                $request->request->all('sort')
            )
        );
    }

    #[Route(path: '/api/_action/snippet/filter', name: 'api.action.snippet.get.filter', methods: ['GET'])]
    public function getFilterItems(Context $context): Response
    {
        $filter = $this->snippetService->getRegionFilterItems($context);

        return new JsonResponse([
            'total' => \count($filter),
            'data' => $filter,
        ]);
    }

    #[Route(path: '/api/_action/snippet-set/baseFile', name: 'api.action.snippet-set.base-file', methods: ['GET'])]
    public function getBaseFiles(): Response
    {
        $files = $this->snippetFileCollection->getFilesArray();

        return new JsonResponse([
            'items' => $files,
            'total' => \count($files),
        ]);
    }

    #[Route(path: '/api/_action/snippet-set/author', name: 'api.action.snippet-set.author', methods: ['GET'])]
    public function getAuthors(Context $context): Response
    {
        $authors = $this->snippetService->getAuthors($context);

        return new JsonResponse([
            'total' => \count($authors),
            'data' => $authors,
        ]);
    }
}
