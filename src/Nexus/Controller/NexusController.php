<?php declare(strict_types=1);

namespace Shopware\Nexus\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class NexusController extends Controller
{
    /**
     * @Route("/nexus", name="nexus")
     */
    public function indexAction()
    {
        $template = $this->get('shopware.storefront.twig.template_finder')->find('nexus/index.html.twig', true);

        return $this->render($template);
    }
}
