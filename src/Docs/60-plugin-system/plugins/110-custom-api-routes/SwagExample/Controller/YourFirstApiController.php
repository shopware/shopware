<?php declare(strict_types=1);

namespace SwagExample\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class YourFirstApiController extends AbstractController
{
    /**
     * @Route("/api/v{version}/_action/swag/my-api-action", name="api.action.swag.my-api-action", methods={"POST"})
     */
    public function yourFirstApiAction(): JsonResponse
    {
        return new JsonResponse(['You successfully created your first API route']);
    }
}
