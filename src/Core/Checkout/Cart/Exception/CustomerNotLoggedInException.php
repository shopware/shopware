<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 */
class CustomerNotLoggedInException extends CartException
{
    /**
     * @deprecated tag:v6.5.0 - Own __construct will be removed, use \Shopware\Core\Checkout\Cart\CartException::customerNotLoggedIn instead
     */
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_NOT_LOGGED_IN_CODE,
            'Customer is not logged in.'
        );
    }
}
