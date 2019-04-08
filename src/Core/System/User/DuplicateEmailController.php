<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DuplicateEmailController extends AbstractController
{
    /**
     * @var DuplicateEmailService
     */
    private $duplicateEmailService;

    public function __construct(
        DuplicateEmailService $duplicateEmailService
    ) {
        $this->duplicateEmailService = $duplicateEmailService;
    }

    /**
     * @Route("api/v{version}/_action/user/check-email-unique", name="api.action.check-email-unique", methods={"POST"})
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function isEmailUnique(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('email')) {
            throw new \InvalidArgumentException('Parameter email missing');
        }

        if (!$request->request->has('id')) {
            throw new \InvalidArgumentException('Parameter id missing');
        }

        $email = $request->request->get('email');
        $id = $request->request->get('id');

        return new JsonResponse(
            ['emailIsUnique' => $this->duplicateEmailService->checkEmailUnique($email, $id, $context)]
        );
    }
}
