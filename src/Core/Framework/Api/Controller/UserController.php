<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends Controller
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
     * @Route("/api/v{version}/user/me", name="api.user.me", methods={"GET"})
     */
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        $userId = $context->getSourceContext()->getUserId();

        $users = $this->userRepository->read(new ReadCriteria([$userId]), $context);

        return $responseFactory->createDetailResponse($users->get($userId), UserDefinition::class, $request, $context);
    }
}
