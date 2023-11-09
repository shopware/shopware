<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CustomerException::noHashProvided instead
 */
#[Package('checkout')]
class NoHashProvidedException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::NO_HASH_PROVIDED,
            'The given hash is empty.'
        );
    }
}
