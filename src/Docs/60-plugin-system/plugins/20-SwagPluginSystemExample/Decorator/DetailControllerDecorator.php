<?php declare(strict_types=1);

namespace SwagExample\Decorator;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Product\Controller\DetailController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DetailControllerDecorator extends AbstractController
{
    /**
     * @var DetailController
     */
    private $originalController;

    public function __construct(DetailController $controller)
    {
        $this->originalController = $controller;
    }

    public function index(string $id, CheckoutContext $context, Request $request): Response
    {
        if ($context->getCustomer()) {
            return $this->originalController->index($id, $context, $request);
        }

        return new RedirectResponse($this->generateUrl('frontend.account.login.page', ['redirectTo' => $request->getRequestUri()]));
    }
}
