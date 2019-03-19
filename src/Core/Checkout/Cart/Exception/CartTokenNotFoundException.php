<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CartTokenNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $token;

    public function __construct(string $token)
    {
        $this->token = $token;

        parent::__construct('Cart with token {{ token }} not found.', ['token' => $token]);
    }

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
