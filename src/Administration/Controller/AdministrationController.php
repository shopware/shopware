<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class AdministrationController extends Controller
{
    /**
     * @Route("/admin", name="administration")
     */
    public function indexAction()
    {
        $template = $this->get('shopware.storefront.twig.template_finder')->find('administration/index.html.twig', true);

        return $this->render($template);
    }
}
