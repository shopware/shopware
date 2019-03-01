<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CartTokenNotFoundException extends ShopwareHttpException
{
    protected $code = 'CART-TOKEN-NOT-FOUND';
    /**
     * @var string
     */
    private $token;

    public function __construct(string $token, $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Cart with token %s not found', $token);
        parent::__construct($message, $code, $previous);
        $this->token = $token;
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
