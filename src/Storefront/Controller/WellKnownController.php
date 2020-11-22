<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @RouteScope(scopes={"storefront"})
 * @Route(".well-known/");
 */
class WellKnownController extends StorefrontController
{
    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @Route("change-password", name="frontend.well-known.change-password", methods={"GET"})
     */
    public function changePassword(): Response
    {
        return new RedirectResponse(
            $this->router->generate('frontend.account.profile.page', [
                '_fragment' => '#profile-password-form',
            ]),
            Response::HTTP_FOUND
        );
    }
}
