<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FinishController extends AbstractController
{
    #[Route('/finish', name: 'finish', defaults: ['step' => 3])]
    public function default(Request $request): Response
    {
        // @codeCoverageIgnoreStart
        if ($request->getMethod() === Request::METHOD_POST) {
            $self = $_SERVER['SCRIPT_FILENAME'];
            \assert(\is_string($self));
            unlink($self);

            return new RedirectResponse($request->getBasePath() . '/admin');
        }
        // @codeCoverageIgnoreEnd

        return $this->render('finish.html.twig');
    }
}
