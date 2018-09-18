<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CsrfTokenController extends Controller
{
    /**
     * @Route("/csrftoken", name="csrftoken", methods={"GET"})
     */
    public function index(): Response
    {
        $token = md5(uniqid('csrf', true));

        return new Response(null, 200, ['X-CSRF-Token' => $token]);
    }
}
