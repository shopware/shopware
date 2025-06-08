<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CustomerNotFoundByIdException extends CustomerException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::CUSTOMER_NOT_FOUND_BY_ID,
            'No matching customer for the id "{{ id }}" was found.',
            ['id' => $id]
        );
    }
}
