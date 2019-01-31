<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Snippet\Services\SnippetServiceInterface;
use Shopware\Core\System\User\UserEntity;
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
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(SnippetServiceInterface $snippetService, EntityRepositoryInterface $userRepository)
    {
        $this->snippetService = $snippetService;
        $this->userRepository = $userRepository;
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

    private function getActiveUsername(Context $context): string
    {
        $userCriteria = new Criteria();
        $userCriteria->addFilter(new EqualsFilter('id', $context->getSourceContext()->getUserId()));
        /** @var UserEntity $currentUser */
        $currentUser = $this->userRepository->search($userCriteria, $context)->first();

        return 'user/' . ($currentUser->getUsername() ?: 'undefined');
    }
}
