<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(EntityRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/api/v{version}/_info/me", name="api.info.me", methods={"GET"})
     */
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        $userId = $context->getSourceContext()->getUserId();

        $users = $this->userRepository->search(new Criteria([$userId]), $context);

        return $responseFactory->createDetailResponse($users->get($userId), UserDefinition::class, $request, $context);
    }
}
