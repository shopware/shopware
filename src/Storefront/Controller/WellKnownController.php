<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(".well-known/", defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
#[Package('storefront')]
class WellKnownController extends StorefrontController
{
    /**
     * @Route("change-password", name="frontend.well-known.change-password", methods={"GET"})
     */
    public function changePassword(): Response
    {
        return $this->redirectToRoute(
            'frontend.account.profile.page',
            ['_fragment' => '#profile-password-form'],
            Response::HTTP_FOUND
        );
    }
}
