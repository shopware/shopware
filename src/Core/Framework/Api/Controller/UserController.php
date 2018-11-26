<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @var RepositoryInterface
     */
    private $userRepository;

    public function __construct(RepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/api/_info/v{version}/me", name="api.info.me", methods={"GET"})
     */
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        $userId = $context->getSourceContext()->getUserId();

        $users = $this->userRepository->read(new ReadCriteria([$userId]), $context);

        return $responseFactory->createDetailResponse($users->get($userId), UserDefinition::class, $request, $context);
    }
}
