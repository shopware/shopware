<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidScopeDefinitionException extends ShopwareHttpException
{
    public function __construct(string $scope, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Invalid discount calculator scope definition "{{ label }}"',
            ['label' => $scope],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_DISCOUNT_SCOPE_DEFINITION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
