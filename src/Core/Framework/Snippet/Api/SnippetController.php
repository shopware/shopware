<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Snippet\Services\SnippetServiceInterface;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
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
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(SnippetServiceInterface $snippetService, RequestCriteriaBuilder $criteriaBuilder, EntityRepositoryInterface $userRepository)
    {
        $this->snippetService = $snippetService;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->userRepository = $userRepository;
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
        if ($request->request->get('isCustom', false)) {
            return new JsonResponse($this->snippetService->getCustomList($criteria, $this->getActiveUsername($context)));
        }

        return new JsonResponse($this->snippetService->getList($criteria, $context));
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
        $response = $this->snippetService->getDbSnippetByKey($translationKey, $this->getActiveUsername($context));

        if ($request->request->get('isCustom', false)) {
            return new JsonResponse($response['data']);
        }
        $response = $this->snippetService->getList($criteria, $context);

        return new JsonResponse($response['data'][$translationKey] ?? false);
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
