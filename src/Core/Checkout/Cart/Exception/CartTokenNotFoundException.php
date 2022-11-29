<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 */
class CartTokenNotFoundException extends CartException
{
    /**
     * @deprecated tag:v6.5.0 - Will be removed, use `getParameter('token')` instead
     */
    private string $token;

    /**
     * @deprecated tag:v6.5.0 - Own __construct will be removed, use \Shopware\Core\Checkout\Cart\CartException::tokenNotFound
     */
    public function __construct(string $token)
    {
        $this->token = $token;

        parent::__construct(Response::HTTP_NOT_FOUND, self::TOKEN_NOT_FOUND_CODE, 'Cart with token {{ token }} not found.', ['token' => $token]);
    }

    //@deprecated tag:v6.5.0 - Remove all code below
    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_TOKEN_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
