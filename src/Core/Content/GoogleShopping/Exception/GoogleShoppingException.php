<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class GoogleShoppingException extends ShopwareHttpException
{
    private $statusCode;

    public function __construct(string $message, int $statusCode)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_EXCEPTION';
    }
}
