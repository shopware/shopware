<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\PhpBinaryFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PhpConfigController extends AbstractController
{
    public function __construct(private readonly PhpBinaryFinder $binaryFinder)
    {
    }

    #[Route('/configure', name: 'configure', defaults: ['step' => 1])]
    public function index(Request $request): Response
    {
        if ($phpBinary = $request->request->get('phpBinary')) {
            $request->getSession()->set('phpBinary', $phpBinary);

            return $this->redirectToRoute('index');
        }

        return $this->render('php_config.html.twig', [
            'phpBinary' => $request->getSession()->get('phpBinary', $this->binaryFinder->find()),
        ]);
    }
}
