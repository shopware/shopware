<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('core')]
class IndexController extends AbstractController
{
    #[Route('/', name: 'index', defaults: ['step' => 0])]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }
}
