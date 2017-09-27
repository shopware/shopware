<?php

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
        return $this->render('@Nexus/index.html.twig');
    }
}
