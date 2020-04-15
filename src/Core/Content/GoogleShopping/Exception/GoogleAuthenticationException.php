<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthenticationException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $error;

    public function __construct(string $error, string $errorMessage)
    {
        parent::__construct($errorMessage);
        $this->error = $error;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getErrorCode(): string
    {
        return $this->error;
    }
}
