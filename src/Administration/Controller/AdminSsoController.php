<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class AdminSsoController extends AbstractController
{
    public function __construct(
    ) {
    }
}
