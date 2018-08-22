<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CustomerAccountExistsException extends ShopwareHttpException
{
    protected $code = 'CUSTOMER-ACCOUNT-EXISTS';

    /**
     * @var string
     */
    private $email;

    public function __construct(string $email, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Customer with email "%s" already has an account', $email);
        parent::__construct($message, $code, $previous);
        $this->email = $email;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
