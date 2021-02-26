<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageOfOrderDeleteException extends ShopwareHttpException
{
    public function __construct(string $language, ?\Throwable $e = null)
    {
        parent::__construct(
            'The language "{{ language }}" cannot be deleted because Orders with this language exist.',
            ['language' => $language],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__LANGUAGE_OF_ORDER_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
