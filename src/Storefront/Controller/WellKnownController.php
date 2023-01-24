<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package storefront
 *
 * @internal
 */
#[Route(path: '.well-known/', defaults: ['_routeScope' => ['storefront']])]
class WellKnownController extends StorefrontController
{
    #[Route(path: 'change-password', name: 'frontend.well-known.change-password', methods: ['GET'])]
    public function changePassword(): Response
    {
        return $this->redirectToRoute(
            'frontend.account.profile.page',
            ['_fragment' => '#profile-password-form'],
            Response::HTTP_FOUND
        );
    }
}
