<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidCartException extends ShopwareHttpException
{
    protected $code = 'INVALID-CART';

    /**
     * @var ErrorCollection
     */
    private $errors;

    public function __construct(ErrorCollection $errors)
    {
        $this->errors = $errors;
        parent::__construct(sprintf(
            'The cart is invalid, got %s error(s). %s',
            $errors->count(),
            print_r($this->formatErrors(), true)
        ));
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function formatErrors(): array
    {
        $output = [];
        /** @var Error $error */
        foreach ($this->errors as $error) {
            $output[$error->getKey()][] = $error->jsonSerialize();
        }

        return $output;
    }
}
