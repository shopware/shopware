<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 */
if (!Feature::isActive('v6.5.0.0')) {
    class InvalidCartException extends ShopwareHttpException
    {
        /**
         * @var ErrorCollection
         */
        private $cartErrors;

        /**
         * @deprecated tag:v6.5.0 - Use \Shopware\Core\Checkout\Cart\CartException::invalidCart instead
         */
        public function __construct(ErrorCollection $errors)
        {
            $this->cartErrors = $errors;

            $message = $errors->map(function (Error $error) {
                return $error->getId() . ': ' . $error->getMessage();
            });

            parent::__construct(
                'The cart is invalid, got {{ errorCount }} error(s).' . \PHP_EOL . \implode(\PHP_EOL, $message),
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
} else {
    class InvalidCartException extends CartException
    {
    }
}
