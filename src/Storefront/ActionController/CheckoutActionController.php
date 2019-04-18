<?php declare(strict_types=1);

namespace Shopware\Storefront\ActionController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\XmlHttpRequestableInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Router;

class CheckoutActionController extends AbstractController implements XmlHttpRequestableInterface
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var Router
     */
    private $router;

    public function __construct(Router $router, CartService $service)
    {
        $this->cartService = $service;
        $this->router = $router;
    }

    /**
     * @Route("/checkout/line-item/{id}", name="frontend.checkout.line-item.delete", methods={"DELETE"})
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws CartTokenNotFoundException
     */
    public function removeLineItem(string $id, Request $request, SalesChannelContext $context): Response
    {
        $token = $request->request->getAlnum('token', $context->getToken());

        $cart = $this->cartService->getCart($token, $context);

        if (!$cart->has($id)) {
            throw new LineItemNotFoundException($id);
        }

        $this->cartService->remove($cart, $id, $context);

        return $this->createResponse($request);
    }

    private function createResponse(Request $request): Response
    {
        if ($request->request->has('redirectTo')) {
            return $this->redirectToRoute($request->request->get('redirectTo'));
        }

        if ($request->request->has('forward')) {
            $url = $this->generateUrl($request->request->get('forward'));

            $route = $this->router->match($url);

            return $this->forward($route['_controller']);
        }

        return new Response();
    }
}
