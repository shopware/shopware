<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidCartException extends ShopwareHttpException
{
    /**
     * @var ErrorCollection
     */
    private $cartErrors;

    public function __construct(ErrorCollection $errors)
    {
        $this->cartErrors = $errors;

        parent::__construct(
            'The cart is invalid, got {{ errorCount }} error(s).',
            ['errorCount' => $errors->count()]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_INVALID';
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->cartErrors as $item) {
            $error = [
                'status' => '400',
                'code' => $item->getCode(),
                'title' => Response::$statusTexts[400] ?? 'unknown status',
                'detail' => $item->getMessage(),
                'meta' => [
                    'parameters' => $item->jsonSerialize(),
                ],
            ];

            if ($withTrace) {
                $error['trace'] = $item->getTrace();
            }

            yield $error;
        }
    }

    public function getCartErrors(): ErrorCollection
    {
        return $this->cartErrors;
    }
}
